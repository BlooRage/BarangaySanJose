<?php
declare(strict_types=1);

session_start();
require_once "../General/connection.php";
require_once "../General/security.php";
require_once "../General/uniqueIDGenerate.php";
require_once "../General/householdMemberVerification.php";

requireRoleSession(['SuperAdmin', 'Official', 'Officials', 'Personnel', 'Personnels', 'Admin', 'Employee']);

header('Content-Type: application/json; charset=utf-8');

hmv_ensure_request_table($conn);

function hmv_fetch_request_row(mysqli $conn, int $requestId): ?array
{
    $usesStatusLookup = hmv_request_uses_status_lookup($conn);
    $statusSelect = $usesStatusLookup
        ? "req.status_id, req.status AS legacy_status, reqs.status_name AS request_status"
        : "NULL AS status_id, req.status AS legacy_status, req.status AS request_status";
    $statusJoin = $usesStatusLookup
        ? "LEFT JOIN statuslookuptbl reqs ON reqs.status_id = req.status_id"
        : "";
    $stmt = $conn->prepare("
        SELECT req.*, {$statusSelect}
        FROM householdmemberverificationtbl req
        {$statusJoin}
        WHERE request_id = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $requestId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function hmv_find_existing_member_id(mysqli $conn, string $headResidentId, string $lastName, string $firstName, ?string $middleName, ?string $suffix, string $birthdate): ?int
{
    $stmt = $conn->prepare("
        SELECT household_member_id
        FROM householdmemberinfotbl
        WHERE fam_head_id = ?
          AND last_name = ?
          AND first_name = ?
          AND (middle_name <=> ?)
          AND (suffix <=> ?)
          AND (birthdate <=> ?)
        LIMIT 1
    ");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('ssssss', $headResidentId, $lastName, $firstName, $middleName, $suffix, $birthdate);
    $stmt->execute();
    $stmt->bind_result($memberId);
    $resolved = $stmt->fetch() ? (int)$memberId : null;
    $stmt->close();
    return $resolved;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['fetch_member_requests'])) {
    try {
        $usesStatusLookup = hmv_request_uses_status_lookup($conn);
        $requestStatusSelect = $usesStatusLookup
            ? "req.status_id, reqs.status_name AS request_status"
            : "NULL AS status_id, req.status AS request_status";
        $requestStatusJoin = $usesStatusLookup
            ? "LEFT JOIN statuslookuptbl reqs ON reqs.status_id = req.status_id"
            : "";
        $requestOrderExpr = $usesStatusLookup ? "COALESCE(reqs.status_name, '')" : "COALESCE(req.status, '')";
        $sql = "
            SELECT
                req.request_id,
                req.fam_head_id,
                req.submitted_by_user_id,
                req.last_name,
                req.first_name,
                req.middle_name,
                req.suffix,
                req.birthdate,
                {$requestStatusSelect},
                req.attachment_id,
                req.review_remarks,
                req.submitted_at,
                req.reviewed_at,
                req.reviewed_by_user_id,
                req.approved_household_member_id,
                head.firstname AS head_firstname,
                head.middlename AS head_middlename,
                head.lastname AS head_lastname,
                head.suffix AS head_suffix,
                uf.file_name,
                uf.file_path,
                uf.upload_timestamp,
                s.status_name AS document_verify_status
            FROM householdmemberverificationtbl req
            LEFT JOIN residentinformationtbl head
                ON head.resident_id = req.fam_head_id
            {$requestStatusJoin}
            LEFT JOIN unifiedfileattachmenttbl uf
                ON uf.attachment_id = req.attachment_id
            LEFT JOIN statuslookuptbl s
                ON s.status_id = uf.status_id_verify
            ORDER BY
                CASE {$requestOrderExpr}
                    WHEN 'PendingReview' THEN 0
                    WHEN 'Rejected' THEN 1
                    WHEN 'Active' THEN 2
                    ELSE 2
                END,
                req.submitted_at DESC,
                req.request_id DESC
        ";
        $res = $conn->query($sql);
        if (!($res instanceof mysqli_result)) {
            throw new RuntimeException('Failed to load household member verification requests.');
        }

        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $row = pii_decrypt_assoc($row, ['head_firstname', 'head_middlename', 'head_lastname', 'head_suffix']) ?? $row;
            $headName = trim(implode(' ', array_values(array_filter([
                (string)($row['head_firstname'] ?? ''),
                (string)($row['head_middlename'] ?? ''),
                (string)($row['head_lastname'] ?? ''),
                (string)($row['head_suffix'] ?? ''),
            ], static fn($value) => trim((string)$value) !== ''))));

            $memberName = trim(implode(' ', array_values(array_filter([
                (string)($row['first_name'] ?? ''),
                (string)($row['middle_name'] ?? ''),
                (string)($row['last_name'] ?? ''),
                (string)($row['suffix'] ?? ''),
            ], static fn($value) => trim((string)$value) !== ''))));

            $rows[] = [
                'request_id' => (int)($row['request_id'] ?? 0),
                'fam_head_id' => (string)($row['fam_head_id'] ?? ''),
                'head_full_name' => $headName,
                'submitted_by_user_id' => (string)($row['submitted_by_user_id'] ?? ''),
                'member_full_name' => $memberName,
                'last_name' => (string)($row['last_name'] ?? ''),
                'first_name' => (string)($row['first_name'] ?? ''),
                'middle_name' => (string)($row['middle_name'] ?? ''),
                'suffix' => (string)($row['suffix'] ?? ''),
                'birthdate' => (string)($row['birthdate'] ?? ''),
                'status' => (string)($row['request_status'] ?? 'PendingReview'),
                'status_id' => (int)($row['status_id'] ?? 0),
                'attachment_id' => (int)($row['attachment_id'] ?? 0),
                'review_remarks' => (string)($row['review_remarks'] ?? ''),
                'submitted_at' => (string)($row['submitted_at'] ?? ''),
                'reviewed_at' => (string)($row['reviewed_at'] ?? ''),
                'reviewed_by_user_id' => (string)($row['reviewed_by_user_id'] ?? ''),
                'approved_household_member_id' => (int)($row['approved_household_member_id'] ?? 0),
                'file_name' => (string)($row['file_name'] ?? ''),
                'file_url' => hmv_to_public_path((string)($row['file_path'] ?? '')),
                'upload_timestamp' => (string)($row['upload_timestamp'] ?? ''),
                'document_verify_status' => (string)($row['document_verify_status'] ?? ''),
            ];
        }
        $res->free();

        echo json_encode(['success' => true, 'data' => $rows]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $action = strtolower(trim((string)($payload['action'] ?? '')));
    $requestId = (int)($payload['request_id'] ?? 0);
    $reviewRemarks = trim((string)($payload['review_remarks'] ?? ''));
    $reviewedByUserId = trim((string)($_SESSION['user_id'] ?? ''));

    if (!in_array($action, ['approve_member_request', 'reject_member_request'], true) || $requestId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid household member verification action.']);
        exit;
    }

    $request = hmv_fetch_request_row($conn, $requestId);
    if (!$request) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Household member verification request not found.']);
        exit;
    }
    $usesStatusLookup = hmv_request_uses_status_lookup($conn);
    $pendingRequestStatusId = $usesStatusLookup ? hmv_get_household_member_status_id($conn, 'PendingReview') : null;
    $activeRequestStatusId = $usesStatusLookup ? hmv_get_household_member_status_id($conn, 'Active') : null;
    $rejectedRequestStatusId = $usesStatusLookup ? hmv_get_household_member_status_id($conn, 'Rejected') : null;
    $currentRequestStatus = (string)($request['request_status'] ?? $request['legacy_status'] ?? $request['status'] ?? '');
    $isPendingRequest = $usesStatusLookup
        ? ((int)($request['status_id'] ?? 0) === (int)$pendingRequestStatusId && $pendingRequestStatusId !== null)
        : ($currentRequestStatus === 'PendingReview');
    if (!$isPendingRequest) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This household member verification request has already been reviewed.']);
        exit;
    }

    $attachmentId = (int)($request['attachment_id'] ?? 0);
    $verifiedStatusId = hmv_get_status_id($conn, 'Verified', 'ResidentDocumentProfiling');
    $rejectedStatusId = hmv_get_status_id($conn, 'Rejected', 'ResidentDocumentProfiling');
    $memberId = 0;

    $conn->begin_transaction();
    try {
        if ($action === 'approve_member_request') {
            $existingMemberId = hmv_find_existing_member_id(
                $conn,
                (string)$request['fam_head_id'],
                (string)$request['last_name'],
                (string)$request['first_name'],
                $request['middle_name'] !== null ? (string)$request['middle_name'] : null,
                $request['suffix'] !== null ? (string)$request['suffix'] : null,
                (string)$request['birthdate']
            );

            if ($existingMemberId !== null && $existingMemberId > 0) {
                $memberId = $existingMemberId;
            } else {
                $memberId = insertHouseholdMemberInfo(
                    $conn,
                    (string)$request['fam_head_id'],
                    (string)$request['last_name'],
                    (string)$request['first_name'],
                    $request['middle_name'] !== null ? (string)$request['middle_name'] : null,
                    $request['suffix'] !== null ? (string)$request['suffix'] : null,
                    (string)$request['birthdate']
                );
            }

            $updateSql = $usesStatusLookup
                ? "
                    UPDATE householdmemberverificationtbl
                    SET status_id = ?,
                        approved_household_member_id = ?,
                        reviewed_by_user_id = ?,
                        review_remarks = ?,
                        reviewed_at = NOW()
                    WHERE request_id = ?
                    LIMIT 1
                "
                : "
                    UPDATE householdmemberverificationtbl
                    SET status = 'Approved',
                        approved_household_member_id = ?,
                        reviewed_by_user_id = ?,
                        review_remarks = ?,
                        reviewed_at = NOW()
                    WHERE request_id = ?
                    LIMIT 1
                ";
            $update = $conn->prepare($updateSql);
            if (!$update) {
                throw new RuntimeException('Failed to approve household member verification request.');
            }
            if ($usesStatusLookup) {
                if ($activeRequestStatusId === null) {
                    $update->close();
                    throw new RuntimeException('Household member status setup is incomplete.');
                }
                $update->bind_param('iissi', $activeRequestStatusId, $memberId, $reviewedByUserId, $reviewRemarks, $requestId);
            } else {
                $update->bind_param('issi', $memberId, $reviewedByUserId, $reviewRemarks, $requestId);
            }
            if (!$update->execute()) {
                $error = $update->error;
                $update->close();
                throw new RuntimeException('Failed to approve household member verification request. ' . $error);
            }
            $update->close();

            if ($attachmentId > 0 && $verifiedStatusId !== null) {
                $docUpdate = $conn->prepare("
                    UPDATE unifiedfileattachmenttbl
                    SET status_id_verify = ?
                    WHERE attachment_id = ?
                    LIMIT 1
                ");
                if ($docUpdate) {
                    $docUpdate->bind_param('ii', $verifiedStatusId, $attachmentId);
                    $docUpdate->execute();
                    $docUpdate->close();
                }
            }
        } else {
            $updateSql = $usesStatusLookup
                ? "
                    UPDATE householdmemberverificationtbl
                    SET status_id = ?,
                        reviewed_by_user_id = ?,
                        review_remarks = ?,
                        reviewed_at = NOW()
                    WHERE request_id = ?
                    LIMIT 1
                "
                : "
                    UPDATE householdmemberverificationtbl
                    SET status = 'Rejected',
                        reviewed_by_user_id = ?,
                        review_remarks = ?,
                        reviewed_at = NOW()
                    WHERE request_id = ?
                    LIMIT 1
                ";
            $update = $conn->prepare($updateSql);
            if (!$update) {
                throw new RuntimeException('Failed to reject household member verification request.');
            }
            if ($usesStatusLookup) {
                if ($rejectedRequestStatusId === null) {
                    $update->close();
                    throw new RuntimeException('Household member status setup is incomplete.');
                }
                $update->bind_param('issi', $rejectedRequestStatusId, $reviewedByUserId, $reviewRemarks, $requestId);
            } else {
                $update->bind_param('ssi', $reviewedByUserId, $reviewRemarks, $requestId);
            }
            if (!$update->execute()) {
                $error = $update->error;
                $update->close();
                throw new RuntimeException('Failed to reject household member verification request. ' . $error);
            }
            $update->close();

            if ($attachmentId > 0 && $rejectedStatusId !== null) {
                $docUpdate = $conn->prepare("
                    UPDATE unifiedfileattachmenttbl
                    SET status_id_verify = ?
                    WHERE attachment_id = ?
                    LIMIT 1
                ");
                if ($docUpdate) {
                    $docUpdate->bind_param('ii', $rejectedStatusId, $attachmentId);
                    $docUpdate->execute();
                    $docUpdate->close();
                }
            }
        }

        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => $action === 'approve_member_request'
                ? 'Household member verification request approved.'
                : 'Household member verification request rejected.',
            'approved_household_member_id' => $memberId,
        ]);
    } catch (Throwable $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

http_response_code(404);
echo json_encode(['success' => false, 'message' => 'Not found']);
