<?php
session_start();
require_once "../General/connection.php";

function normalizeResidentId($value): ?string {
    $id = trim((string)$value);
    if ($id === '' || !preg_match('/^\\d{10}$/', $id)) {
        return null;
    }
    return $id;
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

    // Most records contain absolute filesystem paths; map them by folder marker.
    $marker = '/UnifiedFileAttachment/';
    $markerPos = stripos($normalized, $marker);
    if ($markerPos !== false) {
        $public = substr($normalized, $markerPos);
        return '..' . $public;
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

    $conn->begin_transaction();
    try {
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
            uf.file_name,
            uf.file_path,
            uf.upload_timestamp,
            uf.remarks,
            dt.document_type_name,
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
        $remarks = '';
        if ($reasonText !== '') {
            $remarks = $reasonScope !== ''
                ? trim("scope={$reasonScope}; reason={$reasonText}")
                : $reasonText;
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

        $profileImageUrl = null;
        $residentId = null;
        if ($statusMap[$uiStatus] === 'Verified') {
            $stmt = $conn->prepare("
                SELECT uf.source_id, dt.document_type_name, dt.document_category
                FROM unifiedfileattachmenttbl uf
                INNER JOIN documenttypelookuptbl dt
                    ON uf.document_type_id = dt.document_type_id
                WHERE uf.attachment_id = ?
                LIMIT 1
            ");
            if ($stmt) {
                $stmt->bind_param("i", $attachmentId);
                $stmt->execute();
                $stmt->bind_result($residentId, $docTypeName, $docCategory);
                $stmt->fetch();
                $stmt->close();
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

        echo json_encode([
            'success' => true,
            'status' => $statusMap[$uiStatus],
            'resident_id' => $residentId,
            'profile_image_url' => $profileImageUrl
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

http_response_code(404);
exit("Not found");
