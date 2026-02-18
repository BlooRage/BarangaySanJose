<?php
session_start();
require_once __DIR__ . '/../General/connection.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable']);
    exit;
}

$userId = (string)$_SESSION['user_id'];
$residentId = '';

$stmtResident = $conn->prepare("SELECT resident_id FROM residentinformationtbl WHERE user_id = ? LIMIT 1");
if (!$stmtResident) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load requirements.']);
    exit;
}
$stmtResident->bind_param('s', $userId);
$stmtResident->execute();
$stmtResident->bind_result($residentId);
if (!$stmtResident->fetch() || $residentId === '') {
    $stmtResident->close();
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Resident profile not found.']);
    exit;
}
$stmtResident->close();

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
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load requirements.']);
    exit;
}

$stmt->bind_param('s', $residentId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();

$resolveState = static function (int $pending, int $rejected, int $verified): string {
    if ($pending > 0) return 'pending';
    if ($rejected > 0) return 'rejected';
    if ($verified > 0) return 'verified';
    return 'missing';
};

$pictureState = $resolveState(
    (int)($row['pending_2x2'] ?? 0),
    (int)($row['rejected_2x2'] ?? 0),
    (int)($row['verified_2x2'] ?? 0)
);

$proofState = $resolveState(
    (int)($row['pending_proof'] ?? 0),
    (int)($row['rejected_proof'] ?? 0),
    (int)($row['verified_proof'] ?? 0)
);

$needsPicture = in_array($pictureState, ['missing', 'rejected'], true);
$needsProof = in_array($proofState, ['missing', 'rejected'], true);

echo json_encode([
    'success' => true,
    'resident_id' => $residentId,
    'requirements' => [
        'picture' => [
            'state' => $pictureState,
            'needs_upload' => $needsPicture,
        ],
        'proof' => [
            'state' => $proofState,
            'needs_upload' => $needsProof,
        ],
        'has_required_uploads_pending' => ($pictureState === 'pending' || $proofState === 'pending'),
        'all_required_verified' => ($pictureState === 'verified' && $proofState === 'verified'),
    ],
]);
exit;

