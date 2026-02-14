<?php
session_start();
require_once "../General/connection.php";
require_once "../General/security.php";
require_once "../General/audit.php";

requireRoleSession(['Admin', 'Employee']);

function normalizeResidentId($value): ?string {
    $id = trim((string)$value);
    if ($id === '' || !preg_match('/^\\d{10}$/', $id)) {
        return null;
    }
    return $id;
}

function parseSectorMembershipCsv($value): array {
    $parts = array_map('trim', explode(',', (string)$value));
    $parts = array_values(array_filter($parts, static function ($v) {
        return $v !== '';
    }));

    $seen = [];
    $output = [];
    foreach ($parts as $part) {
        $key = strtolower($part);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $output[] = $part;
    }
    return $output;
}

function mapSectorKeyToLabel($sectorKey): ?string {
    $normalized = strtolower(trim((string)$sectorKey));
    $normalized = preg_replace('/[^a-z]/', '', $normalized);

    $map = [
        'pwd' => 'PWD',
        'seniorcitizen' => 'Senior Citizen',
        'student' => 'Student',
        'indigenouspeople' => 'Indigenous People',
        'indigenousperson' => 'Indigenous People',
        'singleparent' => 'Single Parent'
    ];
    return $map[$normalized] ?? null;
}

function appendSectorMembership(mysqli $conn, string $residentId, string $sectorLabel): ?string {
    $stmt = $conn->prepare("
        SELECT sector_membership
        FROM residentinformationtbl
        WHERE resident_id = ?
        LIMIT 1
    ");
    if (!$stmt) {
        throw new Exception("Prepare failed (read sector membership): " . $conn->error);
    }
    $stmt->bind_param("s", $residentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) return null;

    $sectors = parseSectorMembershipCsv($row['sector_membership'] ?? '');
    $alreadyExists = false;
    foreach ($sectors as $sector) {
        if (strcasecmp($sector, $sectorLabel) === 0) {
            $alreadyExists = true;
            break;
        }
    }
    if (!$alreadyExists) {
        $sectors[] = $sectorLabel;
    }

    $updatedSectorMembership = implode(', ', $sectors);
    $update = $conn->prepare("
        UPDATE residentinformationtbl
        SET sector_membership = ?
        WHERE resident_id = ?
        LIMIT 1
    ");
    if (!$update) {
        throw new Exception("Prepare failed (update sector membership): " . $conn->error);
    }
    $update->bind_param("ss", $updatedSectorMembership, $residentId);
    $update->execute();
    $update->close();

    return $updatedSectorMembership;
}

function upsertSectorMembershipStatus(
    mysqli $conn,
    string $residentId,
    string $sectorKey,
    int $sectorStatusId,
    int $latestAttachmentId,
    ?string $reasonText,
    ?string $uploadTimestamp
): void {
    // If the table doesn't exist or schema doesn't match, silently skip (keeps backward compatibility).
    $stmt = $conn->prepare("
        INSERT INTO residentsectormembershiptbl
            (resident_id, sector_key, sector_status_id, latest_attachment_id, remarks, upload_timestamp, last_update_user_id)
        VALUES
            (?, ?, ?, ?, ?, ?, NULL)
        ON DUPLICATE KEY UPDATE
            sector_status_id = VALUES(sector_status_id),
            latest_attachment_id = VALUES(latest_attachment_id),
            remarks = VALUES(remarks),
            upload_timestamp = VALUES(upload_timestamp),
            last_update_user_id = NULL,
            updated_at = CURRENT_TIMESTAMP
    ");
    if (!$stmt) {
        return;
    }
    $reason = $reasonText !== null && trim($reasonText) !== '' ? $reasonText : null;
    $ts = $uploadTimestamp !== null && trim($uploadTimestamp) !== '' ? $uploadTimestamp : null;
    $stmt->bind_param(
        "ssiiss",
        $residentId,
        $sectorKey,
        $sectorStatusId,
        $latestAttachmentId,
        $reason,
        $ts
    );
    $stmt->execute();
    $stmt->close();
}

	function extractMarkerFromRemarks($remarks): string {
	    $text = trim((string)$remarks);
	    if ($text === '') return '';
	    $parts = explode(';', $text);
	    return trim((string)($parts[0] ?? ''));
	}

	function extractReasonFromRemarks(string $remarks): string {
	    $parts = array_values(array_filter(array_map('trim', explode(';', (string)$remarks))));
	    foreach ($parts as $p) {
	        if (stripos($p, 'reason=') === 0) {
	            return trim(substr($p, strlen('reason=')));
	        }
	    }
	    return '';
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

function getResidentVerificationEligibility(mysqli $conn, string $residentId): array {
    $sql = "
        SELECT
            SUM(
                CASE
                    WHEN dt.document_category = 'ResidentProfiling'
                         AND dt.document_type_name = '2x2 Picture'
                         AND sv.status_name = 'Rejected'
                         AND sv.status_type = 'ResidentDocumentProfiling'
                    THEN 1 ELSE 0
                END
            ) AS rejected_2x2_count,
            SUM(
                CASE
                    WHEN dt.document_category = 'ResidentProfiling'
                         AND dt.document_type_name <> '2x2 Picture'
                         AND (uf.remarks IS NULL OR uf.remarks NOT LIKE 'sector:%')
                         AND sv.status_name = 'Rejected'
                         AND sv.status_type = 'ResidentDocumentProfiling'
                    THEN 1 ELSE 0
                END
            ) AS rejected_supporting_doc_count,
            SUM(
                CASE
                    WHEN dt.document_category = 'ResidentProfiling'
                         AND (uf.remarks IS NULL OR uf.remarks NOT LIKE 'sector:%')
                         AND sv.status_name = 'PendingReview'
                         AND sv.status_type = 'ResidentDocumentProfiling'
                    THEN 1 ELSE 0
                END
            ) AS pending_registration_doc_count
        FROM unifiedfileattachmenttbl uf
        INNER JOIN documenttypelookuptbl dt
            ON uf.document_type_id = dt.document_type_id
        LEFT JOIN statuslookuptbl sv
            ON uf.status_id_verify = sv.status_id
        WHERE uf.source_type = 'ResidentProfiling'
          AND uf.source_id = ?
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed (verification eligibility): " . $conn->error);
    }
    $stmt->bind_param("s", $residentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    $hasPendingRegistrationDocs = (int)($row['pending_registration_doc_count'] ?? 0) > 0;
    $hasRejected2x2 = (int)($row['rejected_2x2_count'] ?? 0) > 0;
    $hasRejectedSupportingDoc = (int)($row['rejected_supporting_doc_count'] ?? 0) > 0;

    return [
        'has_pending_registration_docs' => $hasPendingRegistrationDocs,
        'has_rejected_2x2' => $hasRejected2x2,
        'has_rejected_supporting_doc' => $hasRejectedSupportingDoc,
        'can_approve' => !$hasPendingRegistrationDocs && !$hasRejected2x2 && !$hasRejectedSupportingDoc,
        'can_decline' => !$hasPendingRegistrationDocs
    ];
}

function getResidentRegistrationDocCount(mysqli $conn, string $residentId): int {
    $sql = "
        SELECT COUNT(*) AS doc_count
        FROM unifiedfileattachmenttbl uf
        INNER JOIN documenttypelookuptbl dt
            ON uf.document_type_id = dt.document_type_id
        WHERE uf.source_type = 'ResidentProfiling'
          AND uf.source_id = ?
          AND dt.document_category = 'ResidentProfiling'
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed (registration doc count): " . $conn->error);
    }
    $stmt->bind_param("s", $residentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
    return (int)($row['doc_count'] ?? 0);
}

function getResidentStatusName(mysqli $conn, string $residentId): string {
    $sql = "
        SELECT s.status_name
        FROM residentinformationtbl r
        LEFT JOIN statuslookuptbl s ON r.status_id_resident = s.status_id
        WHERE r.resident_id = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed (resident status): " . $conn->error);
    }
    $stmt->bind_param("s", $residentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
    return trim((string)($row['status_name'] ?? ''));
}

function toPublicPath($path): ?string {
    $path = trim((string)$path);
    if ($path === '') {
        return null;
    }

    $normalized = str_replace("\\", "/", $path);
    $normalized = preg_replace('#/+#', '/', $normalized);

    // Resolve "." and ".." segments so browser URLs stay clean.
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

    // If stored as a full web path, keep it.
    if (strpos($normalized, '/BarangaySanJose/') === 0) {
        return $normalized;
    }

    $webRoot = realpath(__DIR__ . "/../..");
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

/* =====================================================
   1. HANDLE STATUS UPDATE (View Modal)
===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['button-saveStatus'])) {
    http_response_code(403);
    exit("Resident status updates from this modal are disabled.");
    exit;
}

/* =====================================================
   2. HANDLE ARCHIVE RESIDENT (AJAX)
===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_resident_status'])) {
    header('Content-Type: application/json; charset=utf-8');

    $residentId = normalizeResidentId($_POST['resident_id'] ?? '');
    $uiStatus = trim((string)($_POST['new_status'] ?? ''));
    $reasonText = trim((string)($_POST['reason_text'] ?? ''));

    $statusMap = [
        'APPROVED' => 'VerifiedResident',
        'DENIED' => 'NotVerified'
    ];

    if (!$residentId || !isset($statusMap[$uiStatus])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid request.']);
        exit;
    }

    if ($uiStatus === 'DENIED' && $reasonText === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Reason is required when declining.']);
        exit;
    }

    if ($uiStatus === 'APPROVED' || $uiStatus === 'DENIED') {
        try {
            if ($uiStatus === 'APPROVED') {
                $statusName = getResidentStatusName($conn, $residentId);
                $docCount = getResidentRegistrationDocCount($conn, $residentId);
                if ($statusName === 'PendingVerification' && $docCount <= 0) {
                    http_response_code(409);
                    echo json_encode([
                        'success' => false,
                        'code' => 'NO_REGISTRATION_DOCUMENTS',
                        'message' => 'Resident cannot be verified yet. No registration documents are uploaded.'
                    ]);
                    exit;
                }
            }

            $eligibility = getResidentVerificationEligibility($conn, $residentId);
            $isBlocked = ($uiStatus === 'APPROVED' && !$eligibility['can_approve'])
                || ($uiStatus === 'DENIED' && !$eligibility['can_decline']);
            if ($isBlocked) {
                http_response_code(409);
                $actionLabel = $uiStatus === 'DENIED' ? 'declined' : 'verified';
                $message = $uiStatus === 'APPROVED'
                    ? "Resident cannot be {$actionLabel} yet while registration documents are pending review or profile/supporting document is denied."
                    : "Resident cannot be {$actionLabel} yet while submitted registration documents are still pending review.";
                echo json_encode([
                    'success' => false,
                    'code' => 'PENDING_REGISTRATION_DOCUMENTS',
                    'message' => $message
                ]);
                exit;
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Unable to validate verification requirements.']);
            exit;
        }
    }

    $conn->begin_transaction();
    try {
        $actorUserId = isset($_SESSION['user_id']) ? (string)$_SESSION['user_id'] : null;
        $actorRole = (string)($_SESSION['role'] ?? 'Unknown');

        // Capture old status for audit trail.
        $oldResidentStatusName = getResidentStatusName($conn, $residentId);

        $statusName = $statusMap[$uiStatus];
        $stmt = $conn->prepare("
            UPDATE residentinformationtbl
            SET status_id_resident = (
                SELECT status_id
                FROM statuslookuptbl
                WHERE status_name = ?
                  AND status_type = 'Resident'
                LIMIT 1
            )
            WHERE resident_id = ?
            LIMIT 1
        ");
        if (!$stmt) {
            throw new Exception("Prepare failed.");
        }
        $stmt->bind_param("ss", $statusName, $residentId);
        $stmt->execute();
        $stmt->close();

        // If a status remarks column exists, persist decline reason there.
        $remarksColExists = 0;
        $colCheck = $conn->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'residentinformationtbl'
              AND COLUMN_NAME = 'status_remarks'
        ");
        if ($colCheck) {
            $colCheck->execute();
            $colCheck->bind_result($remarksColExists);
            $colCheck->fetch();
            $colCheck->close();
        }

        if ($remarksColExists) {
            $remarksToSave = $uiStatus === 'DENIED' ? $reasonText : null;
            $stmtRemarks = $conn->prepare("
                UPDATE residentinformationtbl
                SET status_remarks = ?
                WHERE resident_id = ?
                LIMIT 1
            ");
            if ($stmtRemarks) {
                $stmtRemarks->bind_param("ss", $remarksToSave, $residentId);
                $stmtRemarks->execute();
                $stmtRemarks->close();
            }
        }

        // Audit (best-effort): resident verification status change.
        $newResidentStatusId = null;
        try {
            $newResidentStatusId = getStatusId($conn, $statusName, "Resident");
        } catch (Throwable $e) {
            $newResidentStatusId = null;
        }
        insertUnifiedAuditLog(
            $conn,
            $actorUserId,
            $actorRole,
            'Resident Masterlist',
            'Resident',
            (string)$residentId,
            'RESIDENT_STATUS_UPDATE',
            'status_id_resident',
            (string)$oldResidentStatusName,
            (string)$statusName,
            ($uiStatus === 'DENIED' ? ("reason=" . $reasonText) : null),
            $newResidentStatusId
        );

        $conn->commit();
        echo json_encode([
            'success' => true,
            'status' => $statusName,
            'resident_id' => $residentId
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update resident status.']);
    }
    exit;
}

if (isset($_GET['validate_verification'])) {
    header('Content-Type: application/json; charset=utf-8');

    $residentId = normalizeResidentId($_GET['resident_id'] ?? '');
    if (!$residentId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid resident ID.']);
        exit;
    }

    try {
        $eligibility = getResidentVerificationEligibility($conn, $residentId);
        $docCount = getResidentRegistrationDocCount($conn, $residentId);
        $statusName = getResidentStatusName($conn, $residentId);
        $hasRegistrationDocuments = $docCount > 0;
        $canApprove = $eligibility['can_approve'];
        if ($statusName === 'PendingVerification' && !$hasRegistrationDocuments) {
            $canApprove = false;
        }
        echo json_encode([
            'success' => true,
            'resident_id' => $residentId,
            'can_approve' => $canApprove,
            'can_decline' => $eligibility['can_decline'],
            'has_pending_registration_docs' => $eligibility['has_pending_registration_docs'],
            'has_rejected_2x2' => $eligibility['has_rejected_2x2'],
            'has_rejected_supporting_doc' => $eligibility['has_rejected_supporting_doc'],
            'has_registration_documents' => $hasRegistrationDocuments
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Unable to validate verification requirements.']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_resident'])) {
    header('Content-Type: application/json; charset=utf-8');

    $residentId = normalizeResidentId($_POST['resident_id'] ?? '');
    if (!$residentId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid resident ID.']);
        exit;
    }

    $conn->begin_transaction();

    try {
        $actorUserId = isset($_SESSION['user_id']) ? (string)$_SESSION['user_id'] : null;
        $actorRole = (string)($_SESSION['role'] ?? 'Unknown');

        $oldResidentStatusName = getResidentStatusName($conn, $residentId);

        // Fetch Archived status id
        $statusId = null;
        $stmt = $conn->prepare("
            SELECT status_id
            FROM statuslookuptbl
            WHERE status_name = 'Archived'
              AND status_type = 'Resident'
            LIMIT 1
        ");
        $stmt->execute();
        $stmt->bind_result($statusId);
        $stmt->fetch();
        $stmt->close();

        if (!$statusId) {
            throw new Exception("Archived status not found. Add it to statuslookuptbl.");
        }

        // Get user_id for archived_at update (if column exists)
        $userId = null;
        $stmt = $conn->prepare("
            SELECT user_id
            FROM residentinformationtbl
            WHERE resident_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $residentId);
        $stmt->execute();
        $stmt->bind_result($userId);
        $stmt->fetch();
        $stmt->close();

        // Update resident status to Archived
        $stmt = $conn->prepare("
            UPDATE residentinformationtbl
            SET status_id_resident = ?
            WHERE resident_id = ?
        ");
        $stmt->bind_param("is", $statusId, $residentId);
        $stmt->execute();
        $stmt->close();

        // Audit (best-effort): archive action.
        insertUnifiedAuditLog(
            $conn,
            $actorUserId,
            $actorRole,
            'Resident Archive',
            'Resident',
            (string)$residentId,
            'RESIDENT_ARCHIVE',
            'status_id_resident',
            (string)$oldResidentStatusName,
            'Archived',
            null,
            (int)$statusId
        );

        // Update archived_at if column exists
        $colExists = 0;
        $stmt = $conn->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'useraccountstbl'
              AND COLUMN_NAME = 'archived_at'
        ");
        $stmt->execute();
        $stmt->bind_result($colExists);
        $stmt->fetch();
        $stmt->close();

        if ($colExists && $userId) {
            $stmt = $conn->prepare("
                UPDATE useraccountstbl
                SET archived_at = NOW()
                WHERE user_id = ?
            ");
            $stmt->bind_param("s", $userId);
            $stmt->execute();
            $stmt->close();
        }

        $conn->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

/* =====================================================
   3. HANDLE EDIT RESIDENT UPDATE (Edit Modal)
===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resident_id'])) {

    $residentId = normalizeResidentId($_POST['resident_id'] ?? '');
    $userId     = trim((string)($_POST['user_id'] ?? ''));

    if (!$residentId) {
        http_response_code(400);
        exit("Invalid resident ID.");
    }

    // -----------------------
    // Personal Info
    // -----------------------
    $firstName   = $_POST['firstName'] ?? '';
    $middleName  = $_POST['middleName'] ?? '';
    $lastName    = $_POST['lastName'] ?? '';
    $suffix      = $_POST['suffix'] ?? '';
    $birthdate   = $_POST['dateOfBirth'] ?? '';
    $sex         = $_POST['sex'] ?? '';
    $civilStatus = $_POST['civilStatus'] ?? '';
    $voterStatus = $_POST['voterStatus'] ?? '';
    $religion    = $_POST['religion'] ?? '';

    // -----------------------
    // Occupation (tinyint + detail)
    // -----------------------
    $occupationText = trim($_POST['occupation'] ?? '');
    $isUnemployed = ($occupationText === '') || (strcasecmp($occupationText, 'Unemployed') === 0);

    if ($isUnemployed) {
        $occupation = 0;
        $occupationDetail = null;
    } else {
        $occupation = 1;
        $occupationDetail = $occupationText;
    }

    // -----------------------
    // Sector membership
    // -----------------------
    $sectorMembership = isset($_POST['sectorMembership'])
        ? implode(",", $_POST['sectorMembership'])
        : '';

    // -----------------------
    // Emergency Contact
    // -----------------------
    $emFirst  = $_POST['emergencyFirstName'] ?? '';
    $emMiddle = $_POST['emergencyMiddleName'] ?? '';
    $emLast   = $_POST['emergencyLastName'] ?? '';
    $emSuffix = $_POST['emergencySuffix'] ?? '';
    $emPhone  = $_POST['emergencyPhoneNumber'] ?? '';
    $emRel    = $_POST['emergencyRelationship'] ?? '';
    $emAddr   = $_POST['emergencyAddress'] ?? '';

    // -----------------------
    // Update residentinformationtbl
    // -----------------------
    $stmt = $conn->prepare("
        UPDATE residentinformationtbl
        SET firstname = ?, middlename = ?, lastname = ?, suffix = ?,
            birthdate = ?, sex = ?, civil_status = ?, voter_status = ?,
            occupation = ?, occupation_detail = ?,
            religion = ?, sector_membership = ?
        WHERE resident_id = ?
    ");

    $stmt->bind_param(
        "sssssssiissss",
        $firstName, $middleName, $lastName, $suffix,
        $birthdate, $sex, $civilStatus, $voterStatus,
        $occupation, $occupationDetail,
        $religion, $sectorMembership,
        $residentId
    );

    $stmt->execute();
    $stmt->close();

    // -----------------------
    // Update emergencycontacttbl
    // -----------------------
    if ($userId !== '') {
        $stmt = $conn->prepare("
            UPDATE emergencycontacttbl
            SET first_name = ?, middle_name = ?, last_name = ?, suffix = ?,
                phone_number = ?, relationship = ?, address = ?
            WHERE user_id = ?
        ");

        $stmt->bind_param(
            "ssssssss",
            $emFirst, $emMiddle, $emLast, $emSuffix,
            $emPhone, $emRel, $emAddr, $userId
        );

        $stmt->execute();
        $stmt->close();

        // -----------------------
        // Update useraccountstbl timestamp
        // -----------------------
        $stmt = $conn->prepare("
            UPDATE useraccountstbl
            SET updated_at = NOW()
            WHERE user_id = ?
        ");
        $stmt->bind_param("s", $userId);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: ../../Admin-End/residentMasterlist.php");
    exit;
}

/* =====================================================
   4. FETCH RESIDENT DATA (AJAX)
===================================================== */
if (isset($_GET['fetch'])) {

    header('Content-Type: application/json; charset=utf-8');

    $search = trim($_GET['search'] ?? '');

    $sql = "
        SELECT
            r.resident_id,
            r.user_id,
            r.firstname,
            r.middlename,
            r.lastname,
            r.suffix,
            r.sex,
            r.birthdate,
            r.civil_status,
            r.head_of_family,
            r.voter_status,
            r.occupation,
            r.occupation_detail,

            CASE
              WHEN r.occupation = 1
                   AND r.occupation_detail IS NOT NULL
                   AND TRIM(r.occupation_detail) <> ''
                THEN r.occupation_detail
              ELSE 'Unemployed'
            END AS occupation_display,

            r.religion,
            r.sector_membership,
            s.status_name AS status,

            (
                SELECT uf.file_path
                FROM unifiedfileattachmenttbl uf
                INNER JOIN documenttypelookuptbl dt
                    ON uf.document_type_id = dt.document_type_id
                INNER JOIN statuslookuptbl sv
                    ON uf.status_id_verify = sv.status_id
                WHERE uf.source_type = 'ResidentProfiling'
                  AND uf.source_id = r.resident_id
                  AND dt.document_type_name = '2x2 Picture'
                  AND dt.document_category = 'ResidentProfiling'
                  AND sv.status_name = 'Verified'
                  AND sv.status_type = 'ResidentDocumentProfiling'
                ORDER BY uf.upload_timestamp DESC, uf.attachment_id DESC
                LIMIT 1
            ) AS id_picture_path,

            a.unit_number,
            a.street_number AS house_number,
            a.street_name,
            a.phase_number,
            a.subdivision,
            a.area_number,
            a.house_ownership,
            a.house_type,
            a.residency_duration,

            CONCAT(
                e.first_name, ' ',
                IFNULL(CONCAT(LEFT(e.middle_name, 1), '. '), ''),
                e.last_name,
                IF(e.suffix IS NOT NULL AND e.suffix != '', CONCAT(' ', e.suffix), '')
            ) AS emergency_full_name,

            e.first_name AS emergency_first_name,
            e.last_name AS emergency_last_name,
            e.middle_name AS emergency_middle_name,
            e.suffix AS emergency_suffix,
            e.phone_number AS emergency_contact_number,
            e.relationship AS emergency_relationship,
            e.address AS emergency_address

        FROM residentinformationtbl r
        LEFT JOIN statuslookuptbl s ON r.status_id_resident = s.status_id
        LEFT JOIN residentaddresstbl a
            ON a.address_id = (
                SELECT a2.address_id
                FROM residentaddresstbl a2
                WHERE a2.resident_id = r.resident_id
                ORDER BY a2.address_id DESC
                LIMIT 1
            )
        LEFT JOIN emergencycontacttbl e ON e.user_id = r.user_id
    ";

    if ($search !== '') {
        $sql .= " WHERE
            (s.status_name <> 'Archived' OR s.status_name IS NULL)
            AND (
              r.resident_id LIKE ?
              OR r.firstname LIKE ?
              OR r.lastname LIKE ?
              OR r.middlename LIKE ?
              OR a.street_number LIKE ?
              OR a.street_name LIKE ?
              OR a.phase_number LIKE ?
              OR a.subdivision LIKE ?
              OR a.area_number LIKE ?
              OR a.unit_number LIKE ?
            )
        ";
    } else {
        $sql .= " WHERE (s.status_name <> 'Archived' OR s.status_name IS NULL)";
    }

    $sql .= " ORDER BY r.resident_id DESC";

    $stmt = $conn->prepare($sql);

    if ($search !== '') {
        $like = "%$search%";
        $stmt->bind_param(
            "ssssssssss",
            $like,
            $like,
            $like,
            $like,
            $like,
            $like,
            $like,
            $like,
            $like,
            $like
        );
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $fullName =
            $row['firstname'] . ' ' .
            ($row['middlename'] ? $row['middlename'][0] . '. ' : '') .
            $row['lastname'] .
            ($row['suffix'] ? ' ' . $row['suffix'] : '');

        $row['full_name'] = trim($fullName);
        $row['id_picture_url'] = toPublicPath($row['id_picture_path'] ?? '');
        $data[] = $row;
    }

    echo json_encode($data);
    exit;
}

/* =====================================================
   5. FETCH SUBMITTED DOCUMENTS (AJAX)
===================================================== */
if (isset($_GET['fetch_documents'])) {

    header('Content-Type: application/json; charset=utf-8');

    $residentId = normalizeResidentId($_GET['resident_id'] ?? '');
    if (!$residentId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid resident ID.']);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT
            uf.attachment_id,
            uf.source_type,
            uf.file_name,
            uf.file_path,
            uf.upload_timestamp,
            uf.remarks,
            uf.id_number,
            dt.document_type_name,
            dt.document_category,
            s.status_name AS verify_status
        FROM unifiedfileattachmenttbl uf
        LEFT JOIN documenttypelookuptbl dt
            ON uf.document_type_id = dt.document_type_id
        LEFT JOIN statuslookuptbl s
            ON uf.status_id_verify = s.status_id
        WHERE uf.source_type = 'ResidentProfiling'
          AND uf.source_id = ?
        ORDER BY uf.upload_timestamp DESC, uf.attachment_id DESC
    ");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Query prepare failed.']);
        exit;
    }

    $stmt->bind_param("s", $residentId);
    $stmt->execute();
    $result = $stmt->get_result();

    $docs = [];
    while ($row = $result->fetch_assoc()) {
        $row['file_url'] = toPublicPath($row['file_path'] ?? '');
        $docs[] = $row;
    }
    $stmt->close();

    echo json_encode($docs);
    exit;
}

/* =====================================================
   6. UPDATE DOCUMENT STATUS (AJAX)
===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_document_status'])) {
    header('Content-Type: application/json; charset=utf-8');

    $attachmentId = (int)($_POST['attachment_id'] ?? 0);
    $uiStatus = trim((string)($_POST['new_status'] ?? ''));
    $reasonScope = trim((string)($_POST['reason_scope'] ?? ''));
    $reasonText = trim((string)($_POST['reason_text'] ?? ''));

    if ($attachmentId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid attachment ID.']);
        exit;
    }

    $statusMap = [
        'APPROVED' => 'Verified',
        'DENIED'   => 'Rejected',
        'PENDING'  => 'PendingReview'
    ];
    if (!isset($statusMap[$uiStatus])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid status.']);
        exit;
    }
    if ($uiStatus === 'DENIED' && $reasonText === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Cause of denial is required.']);
        exit;
    }

	    try {
	        $statusId = getStatusId($conn, $statusMap[$uiStatus], "ResidentDocumentProfiling");

	        $attachmentResidentId = null;
	        $attachmentRemarks = '';
	        $docTypeName = '';
	        $docCategory = '';
	        $attachmentUploadTs = '';
	        $currentStatusName = '';
	        $currentStatusId = 0;

	        $metaStmt = $conn->prepare("
	            SELECT uf.source_id, uf.remarks, uf.upload_timestamp, dt.document_type_name, dt.document_category, s.status_name, uf.status_id_verify
	            FROM unifiedfileattachmenttbl uf
	            LEFT JOIN documenttypelookuptbl dt
	                ON uf.document_type_id = dt.document_type_id
	            LEFT JOIN statuslookuptbl s
	                ON uf.status_id_verify = s.status_id
	            WHERE uf.attachment_id = ?
	            LIMIT 1
	        ");
	        if ($metaStmt) {
	            $metaStmt->bind_param("i", $attachmentId);
	            $metaStmt->execute();
	            $metaStmt->bind_result($attachmentResidentId, $attachmentRemarks, $attachmentUploadTs, $docTypeName, $docCategory, $currentStatusName, $currentStatusId);
	            $metaStmt->fetch();
	            $metaStmt->close();
	        }

	        $requestedStatusName = $statusMap[$uiStatus]; // Verified | Rejected | PendingReview
	        $currentLower = strtolower(trim((string)$currentStatusName));
	        $isTerminal = in_array($currentLower, ['verified', 'rejected', 'denied'], true);
	        if ($isTerminal) {
	            $currentTerminal = $currentLower === 'verified' ? 'Verified' : 'Rejected';
	            if (strcasecmp($requestedStatusName, $currentTerminal) !== 0) {
	                http_response_code(409);
	                echo json_encode([
	                    'success' => false,
	                    'message' => "This document is already {$currentTerminal} and can no longer be changed. Upload a new document instead."
	                ]);
	                exit;
	            }

	            // Terminal + same-status: do not mutate (keeps rejection reason immutable too).
	            if ($currentTerminal === 'Rejected' && $uiStatus === 'DENIED' && $reasonText !== '') {
	                $existingReason = extractReasonFromRemarks((string)$attachmentRemarks);
	                if ($existingReason !== '' && trim($existingReason) !== trim($reasonText)) {
	                    http_response_code(409);
	                    echo json_encode([
	                        'success' => false,
	                        'message' => "This document is already Rejected and the rejection reason cannot be changed."
	                    ]);
	                    exit;
	                }
	            }

	            echo json_encode([
	                'success' => true,
	                'status' => $currentTerminal,
	                'resident_id' => $attachmentResidentId,
	                'profile_image_url' => null,
	                'sector_membership' => null
	            ]);
	            exit;
	        }

	        // Preserve technical markers in remarks (e.g. idFront/idBack, sector:KEY).
	        // Store denial reason as structured data: "<marker>; reason=...".
	        $marker = extractMarkerFromRemarks($attachmentRemarks);
	        $remarks = $marker;
	        if ($uiStatus === 'DENIED') {
	            $chunks = [];
	            if ($marker !== '') $chunks[] = $marker;
	            if ($reasonScope !== '') $chunks[] = "scope={$reasonScope}";
	            // Used by scheduled cleanup jobs (delete rejected docs after retention period).
	            $chunks[] = "rejected_at=" . date('Y-m-d H:i:s');
	            $chunks[] = "reason={$reasonText}";
	            $remarks = implode('; ', $chunks);
	        }

	        $stmt = $conn->prepare("
	            UPDATE unifiedfileattachmenttbl
	            SET status_id_verify = ?, remarks = ?
	            WHERE attachment_id = ?
	            LIMIT 1
	        ");
	        if (!$stmt) throw new Exception("Prepare failed (update document status): " . $conn->error);
	        $stmt->bind_param("isi", $statusId, $remarks, $attachmentId);
	        $stmt->execute();
	        $stmt->close();

	        // Audit: document status change (best-effort).
	        $actorUserId = isset($_SESSION['user_id']) ? (string)$_SESSION['user_id'] : null;
	        $actorRole = (string)($_SESSION['role'] ?? 'Unknown');
	        $markerForAudit = extractMarkerFromRemarks($attachmentRemarks);
	        $auditRemarks = $markerForAudit;
	        if ($uiStatus === 'DENIED') {
	            $auditRemarks = trim($markerForAudit . ' | scope=' . $reasonScope . ' | reason=' . $reasonText);
	        }
	        insertUnifiedAuditLog(
	            $conn,
	            $actorUserId,
	            $actorRole,
	            'Resident Masterlist',
	            'UnifiedFileAttachment',
	            (string)$attachmentId,
	            'DOCUMENT_STATUS_UPDATE',
	            'status_id_verify',
	            (string)$currentStatusName,
	            (string)$statusMap[$uiStatus],
	            $auditRemarks,
	            (int)$statusId
	        );

        $profileImageUrl = null;
        $residentId = null;
        $updatedSectorMembership = null;
	        if ($statusMap[$uiStatus] === 'Verified') {
	            $residentId = $attachmentResidentId;

	            $remarksLower = strtolower(trim((string)$attachmentRemarks));
	            if ($residentId && strpos($remarksLower, 'sector:') === 0) {
	                $sectorKeyRaw = trim(substr((string)$attachmentRemarks, strlen('sector:')));
	                $sectorKey = trim((string)(explode(':', $sectorKeyRaw, 2)[0] ?? ''));
	                $sectorSide = strtolower(trim((string)(explode(':', $sectorKeyRaw, 3)[1] ?? '')));
	                $sectorLabel = mapSectorKeyToLabel($sectorKey);

	                $statusIdForSectorMembership = (int)$statusId;
	                if (($sectorSide === 'front' || $sectorSide === 'back') && $sectorKey !== '') {
	                    // For ID-like sector proofs saved as front/back, only mark the sector verified
	                    // once BOTH sides' latest uploads are verified.
	                    $getLatestSideStatus = static function (mysqli $conn, string $residentId, string $sectorKey, string $side): ?string {
	                        $pattern = "sector:" . $sectorKey . ":" . $side . "%";
	                        $stmt = $conn->prepare("
	                            SELECT s.status_name
	                            FROM unifiedfileattachmenttbl uf
	                            LEFT JOIN statuslookuptbl s
	                                ON uf.status_id_verify = s.status_id
	                            WHERE uf.source_type = 'ResidentProfiling'
	                              AND uf.source_id = ?
	                              AND uf.remarks LIKE ?
	                            ORDER BY uf.upload_timestamp DESC, uf.attachment_id DESC
	                            LIMIT 1
	                        ");
	                        if (!$stmt) return null;
	                        $stmt->bind_param("ss", $residentId, $pattern);
	                        $stmt->execute();
	                        $row = $stmt->get_result()->fetch_assoc();
	                        $stmt->close();
	                        return $row ? (string)($row['status_name'] ?? '') : null;
	                    };

	                    $frontStatus = strtolower(trim((string)$getLatestSideStatus($conn, (string)$residentId, (string)$sectorKey, 'front')));
	                    $backStatus = strtolower(trim((string)$getLatestSideStatus($conn, (string)$residentId, (string)$sectorKey, 'back')));
	                    $bothVerified = ($frontStatus === 'verified' && $backStatus === 'verified');
	                    if (!$bothVerified) {
	                        $statusIdForSectorMembership = getStatusId($conn, "PendingReview", "ResidentDocumentProfiling");
	                    } elseif ($sectorLabel) {
	                        // Capture old/new for audit trail.
	                        $oldSectorMembership = null;
	                        $qOld = $conn->prepare("SELECT sector_membership FROM residentinformationtbl WHERE resident_id = ? LIMIT 1");
	                        if ($qOld) {
	                            $qOld->bind_param("s", $residentId);
	                            $qOld->execute();
	                            $rOld = $qOld->get_result()->fetch_assoc();
	                            $qOld->close();
	                            $oldSectorMembership = $rOld ? (string)($rOld['sector_membership'] ?? '') : null;
	                        }
	                        $updatedSectorMembership = appendSectorMembership($conn, $residentId, $sectorLabel);
	                        insertUnifiedAuditLog(
	                            $conn,
	                            $actorUserId,
	                            $actorRole,
	                            'Resident Masterlist',
	                            'Resident',
	                            (string)$residentId,
	                            'RESIDENT_FIELD_UPDATE',
	                            'sector_membership',
	                            (string)$oldSectorMembership,
	                            (string)$updatedSectorMembership,
	                            "Auto-append sector after verification: {$sectorLabel}",
	                            null
	                        );
	                    }
	                } elseif ($sectorLabel) {
	                    $oldSectorMembership = null;
	                    $qOld = $conn->prepare("SELECT sector_membership FROM residentinformationtbl WHERE resident_id = ? LIMIT 1");
	                    if ($qOld) {
	                        $qOld->bind_param("s", $residentId);
	                        $qOld->execute();
	                        $rOld = $qOld->get_result()->fetch_assoc();
	                        $qOld->close();
	                        $oldSectorMembership = $rOld ? (string)($rOld['sector_membership'] ?? '') : null;
	                    }
	                    $updatedSectorMembership = appendSectorMembership($conn, $residentId, $sectorLabel);
	                    insertUnifiedAuditLog(
	                        $conn,
	                        $actorUserId,
	                        $actorRole,
	                        'Resident Masterlist',
	                        'Resident',
	                        (string)$residentId,
	                        'RESIDENT_FIELD_UPDATE',
	                        'sector_membership',
	                        (string)$oldSectorMembership,
	                        (string)$updatedSectorMembership,
	                        "Auto-append sector after verification: {$sectorLabel}",
	                        null
	                    );
	                }
	                upsertSectorMembershipStatus(
	                    $conn,
	                    (string)$residentId,
	                    (string)$sectorKey,
	                    (int)$statusIdForSectorMembership,
	                    (int)$attachmentId,
	                    null,
	                    (string)$attachmentUploadTs
	                );
	            }

            if ($residentId && $docTypeName === '2x2 Picture' && $docCategory === 'ResidentProfiling') {
                $stmt = $conn->prepare("
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
                if ($stmt) {
                    $stmt->bind_param("s", $residentId);
                    $stmt->execute();
                    $stmt->bind_result($verifiedPicPath);
                    if ($stmt->fetch() && !empty($verifiedPicPath)) {
                        $profileImageUrl = toPublicPath($verifiedPicPath);
                    }
                    $stmt->close();
                }
            }
        }
	        if ($statusMap[$uiStatus] === 'Rejected') {
	            $residentId = $attachmentResidentId;
	            $remarksLower = strtolower(trim((string)$attachmentRemarks));
	            if ($residentId && strpos($remarksLower, 'sector:') === 0) {
	                $sectorKeyRaw = trim(substr((string)$attachmentRemarks, strlen('sector:')));
	                $sectorKey = trim((string)(explode(':', $sectorKeyRaw, 2)[0] ?? ''));
	                upsertSectorMembershipStatus(
	                    $conn,
	                    (string)$residentId,
	                    (string)$sectorKey,
                    (int)$statusId,
                    (int)$attachmentId,
                    $reasonText !== '' ? $reasonText : null,
                    (string)$attachmentUploadTs
                );
            }
        }
	        if ($statusMap[$uiStatus] === 'PendingReview') {
	            $residentId = $attachmentResidentId;
	            $remarksLower = strtolower(trim((string)$attachmentRemarks));
	            if ($residentId && strpos($remarksLower, 'sector:') === 0) {
	                $sectorKeyRaw = trim(substr((string)$attachmentRemarks, strlen('sector:')));
	                $sectorKey = trim((string)(explode(':', $sectorKeyRaw, 2)[0] ?? ''));
	                upsertSectorMembershipStatus(
	                    $conn,
	                    (string)$residentId,
	                    (string)$sectorKey,
                    (int)$statusId,
                    (int)$attachmentId,
                    null,
                    (string)$attachmentUploadTs
                );
            }
        }

        echo json_encode([
            'success' => true,
            'status' => $statusMap[$uiStatus],
            'resident_id' => $residentId,
            'profile_image_url' => $profileImageUrl,
            'sector_membership' => $updatedSectorMembership
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

http_response_code(404);
exit("Not found");
