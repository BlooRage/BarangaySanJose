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

/* =====================================================
   1. HANDLE STATUS UPDATE (View Modal)
===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['button-saveStatus'])) {

    $residentId = normalizeResidentId($_POST['input-appId'] ?? '');
    $uiStatus   = $_POST['select-newStatus'] ?? '';

    $statusMap = [
        'PENDING'  => 'PendingVerification',
        'APPROVED' => 'VerifiedResident',
        'DENIED'   => 'NotVerified'
    ];

    if (!$residentId || !isset($statusMap[$uiStatus])) {
        http_response_code(400);
        exit("Invalid request");
    }

    $dbStatus = $statusMap[$uiStatus];

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
    ");

    $stmt->bind_param("ss", $dbStatus, $residentId);
    $stmt->execute();
    $stmt->close();

    header("Location: ../../Admin-End/residentMasterlist.php");
    exit;
}

/* =====================================================
   2. HANDLE ARCHIVE RESIDENT (AJAX)
===================================================== */
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

            a.street_number AS house_number,
            a.street_name,
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
                r.resident_id LIKE ? OR
                r.firstname LIKE ? OR
                r.lastname LIKE ? OR
                CONCAT(r.firstname, ' ', r.lastname) LIKE ?
            )
        ";
    } else {
        $sql .= " WHERE (s.status_name <> 'Archived' OR s.status_name IS NULL)";
    }

    $sql .= " ORDER BY r.resident_id DESC";

    $stmt = $conn->prepare($sql);

    if ($search !== '') {
        $like = "%$search%";
        $stmt->bind_param("ssss", $like, $like, $like, $like);
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
        $data[] = $row;
    }

    echo json_encode($data);
    exit;
}

http_response_code(404);
exit("Not found");
