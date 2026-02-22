<?php
declare(strict_types=1);

require_once __DIR__ . '/../General/security.php';
require_once __DIR__ . '/../General/connection.php';
require_once __DIR__ . '/../General/documentRequestWorkflow.php';
require_once __DIR__ . '/../../composer-email-handler/vendor/autoload.php';

requireRoleSession(['SuperAdmin', 'Official', 'Officials', 'Personnel', 'Personnels', 'Admin', 'Employee'], true);

dr_ensure_table($conn);
dr_ensure_general_fees_table($conn);

$action = strtolower(trim((string)($_REQUEST['action'] ?? '')));
if ($action === '') {
    dr_respond_json(400, ['success' => false, 'message' => 'Missing action.']);
}

$currentUserId = (string)($_SESSION['user_id'] ?? '');

function dra_h(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

function dra_format_full_name_from_payload(array $payload): string {
    $last = trim((string)($payload['last_name'] ?? $payload['lastname'] ?? ''));
    $first = trim((string)($payload['first_name'] ?? $payload['firstname'] ?? ''));
    $middle = trim((string)($payload['middle_name'] ?? $payload['middlename'] ?? ''));
    $suffix = trim((string)($payload['suffix'] ?? ''));
    $mi = $middle !== '' ? strtoupper(substr($middle, 0, 1)) . '.' : '';
    $parts = array_filter([$last !== '' ? $last . ',' : '', $first, $mi, $suffix], fn($x) => trim((string)$x) !== '');
    return trim(implode(' ', $parts));
}

function dra_generate_issued_document(array $requestRow): ?string {
    $baseDir = realpath(__DIR__ . '/../../');
    if ($baseDir === false) {
        return null;
    }
    $outDir = $baseDir . '/UnifiedFileAttachment/IssuedDocuments/Generated';
    if (!is_dir($outDir)) {
        @mkdir($outDir, 0775, true);
    }
    $qrDir = $baseDir . '/UnifiedFileAttachment/IssuedDocuments/QR';
    if (!is_dir($qrDir)) {
        @mkdir($qrDir, 0775, true);
    }

    $requestId = trim((string)($requestRow['request_id'] ?? ''));
    if ($requestId === '') {
        return null;
    }
    $docType = trim((string)($requestRow['document_type'] ?? 'Certificate'));
    $purpose = trim((string)($requestRow['purpose'] ?? ''));
    $issuedAt = date('F j, Y');
    $payload = json_decode((string)($requestRow['payload_json'] ?? '{}'), true);
    if (!is_array($payload)) {
        $payload = [];
    }

    $fullName = dra_format_full_name_from_payload($payload);
    if ($fullName === '') {
        $fullName = trim((string)($requestRow['resident_id'] ?? 'Resident'));
    }
    $address = trim((string)($payload['full_address'] ?? 'Barangay San Jose, Rodriguez, Rizal'));
    if ($address === '') {
        $address = 'Barangay San Jose, Rodriguez, Rizal';
    }
    $certNo = trim((string)($requestRow['certificate_number'] ?? ''));
    $orNo = trim((string)($requestRow['or_number'] ?? ''));

    $verificationCode = trim((string)($requestRow['verification_code'] ?? ''));
    $baseUrl = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
        . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $verifyUrl = $baseUrl
        . '/BarangaySanJose/Guest-End/TransactionInformation.html?request_id='
        . rawurlencode($requestId)
        . '&vc=' . rawurlencode($verificationCode !== '' ? $verificationCode : $requestId);

    $qrFile = 'qr_' . preg_replace('/[^A-Za-z0-9_-]/', '', $requestId) . '.png';
    $qrDiskPath = $qrDir . '/' . $qrFile;
    $qrPublicPath = '/UnifiedFileAttachment/IssuedDocuments/QR/' . $qrFile;

    $qrApi = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . rawurlencode($verifyUrl);
    $ctx = stream_context_create([
        'http' => ['timeout' => 6, 'ignore_errors' => true],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $qrContent = @file_get_contents($qrApi, false, $ctx);
    if ($qrContent !== false && strlen($qrContent) > 500) {
        @file_put_contents($qrDiskPath, $qrContent);
    } else {
        // Fallback QR placeholder if external API is unreachable.
        if (function_exists('imagecreatetruecolor')) {
            $img = imagecreatetruecolor(220, 220);
            $white = imagecolorallocate($img, 255, 255, 255);
            $black = imagecolorallocate($img, 0, 0, 0);
            imagefilledrectangle($img, 0, 0, 220, 220, $white);
            imagerectangle($img, 0, 0, 219, 219, $black);
            imagestring($img, 4, 78, 90, 'QR', $black);
            imagestring($img, 2, 12, 198, substr($verificationCode !== '' ? $verificationCode : $requestId, 0, 28), $black);
            imagepng($img, $qrDiskPath);
            imagedestroy($img);
        }
    }

    $fileName = 'issued_' . preg_replace('/[^A-Za-z0-9_-]/', '', $requestId) . '_' . date('YmdHis') . '.pdf';
    $diskPath = $outDir . '/' . $fileName;

    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->SetMargins(18, 16, 18);
    $pdf->AddPage();

    $leftLogo = $baseDir . '/Images/San_Jose_LOGO.jpg';
    if (is_file($leftLogo)) {
        $pdf->Image($leftLogo, 18, 14, 26, 26);
    }
    if (is_file($qrDiskPath)) {
        $pdf->Image($qrDiskPath, 166, 14, 26, 26);
    }

    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 5, 'REPUBLIC OF THE PHILIPPINES', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 5, 'PROVINCE OF RIZAL', 0, 1, 'C');
    $pdf->Cell(0, 5, 'MUNICIPALITY OF RODRIGUEZ', 0, 1, 'C');
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 7, 'BARANGAY SAN JOSE', 0, 1, 'C');
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 6, 'OFFICE OF THE PUNONG BARANGAY', 0, 1, 'C');
    $pdf->Ln(3);
    $pdf->Line(18, $pdf->GetY(), 192, $pdf->GetY());
    $pdf->Ln(8);

    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 8, strtoupper($docType), 0, 1, 'C');
    $pdf->Ln(6);

    $pdf->SetFont('Arial', '', 12);
    $pdf->MultiCell(0, 7, 'This is to certify that ' . $fullName . ' is a bona fide resident of ' . $address . '.', 0, 'J');
    $pdf->Ln(2);
    $pdf->MultiCell(0, 7, 'This certification is issued upon request for ' . ($purpose !== '' ? $purpose : 'legal purpose') . '.', 0, 'J');
    $pdf->Ln(2);
    $pdf->MultiCell(0, 7, 'Issued this ' . $issuedAt . ' at Barangay San Jose, Rodriguez, Rizal.', 0, 'J');

    $pdf->Ln(10);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, 'Request ID: ' . $requestId, 0, 1, 'L');
    if ($certNo !== '') {
        $pdf->Cell(0, 6, 'Certificate No: ' . $certNo, 0, 1, 'L');
    }
    if ($orNo !== '') {
        $pdf->Cell(0, 6, 'OR No: ' . $orNo, 0, 1, 'L');
    }
    $pdf->Cell(0, 6, 'Verify via QR or: ' . $verifyUrl, 0, 1, 'L');

    $pdf->SetY(250);
    $pdf->Line(18, 250, 88, 250);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(70, 7, 'HON. GLENN S. EVANGELISTA', 0, 1, 'L');
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(70, 6, 'Punong Barangay', 0, 1, 'L');

    $pdf->Output('F', $diskPath);

    return '/UnifiedFileAttachment/IssuedDocuments/Generated/' . $fileName;
}

function dra_backfill_payment_verified_to_ready(mysqli $conn): void {
    try {
        $legacyStage = DR_STAGE_PAYMENT_VERIFIED;
        $stmt = $conn->prepare("SELECT * FROM documentrequesttbl WHERE stage = ? ORDER BY request_id ASC");
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('s', $legacyStage);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();

        foreach ($rows as $row) {
            $requestId = (string)($row['request_id'] ?? '');
            if ($requestId === '') {
                continue;
            }
            $issuedPath = trim((string)($row['issued_file_path'] ?? ''));
            if ($issuedPath === '') {
                $issuedPath = dra_generate_issued_document($row) ?? '';
            }
            $patch = [
                'ready_at' => dr_now(),
            ];
            if ($issuedPath !== '') {
                $patch['issued_file_path'] = $issuedPath;
            }
            dr_update_stage($conn, $requestId, DR_STAGE_READY_FOR_CLAIM, $patch);
        }
    } catch (Throwable $e) {
        // best-effort migration only
    }
}

function dra_save_upload(array $file, string $folder): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    $orig = (string)($file['name'] ?? '');
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'webp'], true)) {
        return null;
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    if (!is_uploaded_file($tmp)) {
        return null;
    }

    $baseDir = realpath(__DIR__ . '/../../');
    if ($baseDir === false) {
        return null;
    }

    $targetDir = $baseDir . '/UnifiedFileAttachment/' . trim($folder, '/');
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0775, true);
    }

    $name = date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $target = $targetDir . '/' . $name;
    if (!move_uploaded_file($tmp, $target)) {
        return null;
    }

    return '/UnifiedFileAttachment/' . trim($folder, '/') . '/' . $name;
}

dra_backfill_payment_verified_to_ready($conn);

if ($action === 'list') {
    $where = [];
    $types = '';
    $vals = [];

    $stage = trim((string)($_GET['stage'] ?? ''));
    if ($stage !== '') {
        $where[] = 'stage = ?';
        $types .= 's';
        $vals[] = $stage;
    }

    $search = trim((string)($_GET['q'] ?? ''));
    if ($search !== '') {
        $where[] = '(request_id LIKE ? OR resident_id LIKE ? OR document_type LIKE ?)';
        $types .= 'sss';
        $vals[] = '%' . $search . '%';
        $vals[] = '%' . $search . '%';
        $vals[] = '%' . $search . '%';
    }

    $sql = 'SELECT * FROM documentrequesttbl';
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY submitted_at DESC, request_id DESC';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        dr_respond_json(500, ['success' => false, 'message' => 'Failed to prepare list query.']);
    }

    if ($types !== '') {
        $refs = [];
        foreach ($vals as $i => $v) {
            $refs[$i] = &$vals[$i];
        }
        array_unshift($refs, $types);
        call_user_func_array([$stmt, 'bind_param'], $refs);
    }

    $stmt->execute();
    $items = [];
    $rs = $stmt->get_result();
    while ($row = $rs->fetch_assoc()) {
        $row['stage_label'] = dr_stage_label((string)$row['stage']);
        $row['fee_amount'] = dr_get_fee_amount_for_document_type($conn, (string)($row['document_type'] ?? ''));
        $payload = json_decode((string)($row['payload_json'] ?? '{}'), true);
        $row['payload'] = is_array($payload) ? $payload : [];
        $items[] = $row;
    }
    $stmt->close();

    dr_respond_json(200, ['success' => true, 'items' => $items]);
}

$requestId = trim((string)($_POST['request_id'] ?? $_GET['request_id'] ?? ''));
if ($action === 'view_payment_proof') {
    if ($requestId === '') {
        http_response_code(422);
        exit('Missing request ID.');
    }
    $row = dr_fetch_request($conn, $requestId);
    if (!$row) {
        http_response_code(404);
        exit('Request not found.');
    }
    $publicPath = trim((string)($row['payment_proof_path'] ?? ''));
    if ($publicPath === '') {
        http_response_code(404);
        exit('Payment proof not found.');
    }

    $baseDir = realpath(__DIR__ . '/../../');
    if ($baseDir === false) {
        http_response_code(500);
        exit('Path resolution failed.');
    }
    $relative = '/' . ltrim(preg_replace('#^/BarangaySanJose#', '', $publicPath), '/');
    $absolute = realpath($baseDir . $relative);
    if ($absolute === false || !is_file($absolute) || strpos($absolute, $baseDir . '/UnifiedFileAttachment/') !== 0) {
        http_response_code(404);
        exit('File not found.');
    }

    $mime = (string)(mime_content_type($absolute) ?: 'application/octet-stream');
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . basename($absolute) . '"');
    header('Content-Length: ' . filesize($absolute));
    readfile($absolute);
    exit;
}

if ($requestId === '' && $action !== 'list') {
    dr_respond_json(422, ['success' => false, 'message' => 'Missing request ID.']);
}

$row = $requestId !== '' ? dr_fetch_request($conn, $requestId) : null;
if ($requestId !== '' && !$row) {
    dr_respond_json(404, ['success' => false, 'message' => 'Request not found.']);
}

if ($action === 'personnel_approve') {
    $updated = dr_update_stage($conn, $requestId, DR_STAGE_FOR_PAYMENT, [
        'status_reason' => null,
        'personnel_user_id' => $currentUserId,
        'personnel_decision_at' => dr_now(),
    ]);
    if (!$updated) {
        dr_respond_json(500, ['success' => false, 'message' => 'Unable to approve request.']);
    }

    dr_send_notification(
        $conn,
        $updated,
        'Document Request Approved for Payment',
        'Your request ' . $requestId . ' has been approved and is now waiting for payment.'
    );

    dr_respond_json(200, ['success' => true, 'request' => $updated]);
}

if ($action === 'personnel_reject') {
    $reason = trim((string)($_POST['reason'] ?? ''));
    if ($reason === '') {
        dr_respond_json(422, ['success' => false, 'message' => 'Rejection reason is required.']);
    }

    $updated = dr_update_stage($conn, $requestId, DR_STAGE_REJECTED, [
        'status_reason' => $reason,
        'personnel_user_id' => $currentUserId,
        'personnel_decision_at' => dr_now(),
    ]);

    if (!$updated) {
        dr_respond_json(500, ['success' => false, 'message' => 'Unable to reject request.']);
    }

    dr_send_notification(
        $conn,
        $updated,
        'Document Request Rejected',
        'Your request ' . $requestId . ' was rejected. Reason: ' . $reason
    );

    dr_respond_json(200, ['success' => true, 'request' => $updated]);
}

if ($action === 'finance_verify') {
    $amountRaw = trim((string)($_POST['amount'] ?? ''));
    $orNumber = trim((string)($_POST['or_number'] ?? ''));
    $defaultFee = dr_get_fee_amount_for_document_type($conn, (string)($row['document_type'] ?? ''));

    if ($amountRaw === '' && $defaultFee !== null) {
        $amountRaw = (string)$defaultFee;
    }
    if ($amountRaw === '' || !is_numeric($amountRaw) || (float)$amountRaw < 0) {
        dr_respond_json(422, ['success' => false, 'message' => 'Valid amount is required.']);
    }
    if ($orNumber === '') {
        dr_respond_json(422, ['success' => false, 'message' => 'OR number is required.']);
    }

    $certificateNumber = dr_make_certificate_number($orNumber);
    $verificationCode = strtoupper(bin2hex(random_bytes(8)));
    $qrCodePath = '/UnifiedFileAttachment/IssuedDocuments/QR/qr_' . preg_replace('/[^A-Za-z0-9_-]/', '', $requestId) . '.png';
    $issuedPath = dra_generate_issued_document(array_merge((array)$row, [
        'or_number' => $orNumber,
        'certificate_number' => $certificateNumber,
        'verification_code' => $verificationCode,
    ]));

    $patch = [
        'amount' => (float)$amountRaw,
        'or_number' => $orNumber,
        'certificate_number' => $certificateNumber,
        'verification_code' => $verificationCode,
        'qr_code_path' => $qrCodePath,
        'status_reason' => null,
        'finance_user_id' => $currentUserId,
        'finance_decision_at' => dr_now(),
        'ready_at' => dr_now(),
    ];
    if ($issuedPath !== null && $issuedPath !== '') {
        $patch['issued_file_path'] = $issuedPath;
    }

    // Payment verification immediately makes the document ready for claim/download.
    $updated = dr_update_stage($conn, $requestId, DR_STAGE_READY_FOR_CLAIM, $patch);

    if (!$updated) {
        dr_respond_json(500, ['success' => false, 'message' => 'Unable to verify payment.']);
    }

    dr_send_notification(
        $conn,
        $updated,
        'Payment Verified - Document Ready',
        'Payment for request ' . $requestId . ' is verified. OR: ' . $orNumber . '. Certificate no: ' . $certificateNumber . '. Your document is now ready for claim/download.'
    );

    dr_respond_json(200, ['success' => true, 'request' => $updated]);
}

if ($action === 'finance_reject') {
    $reason = trim((string)($_POST['reason'] ?? ''));
    if ($reason === '') {
        dr_respond_json(422, ['success' => false, 'message' => 'Rejection reason is required.']);
    }

    $updated = dr_update_stage($conn, $requestId, DR_STAGE_PAYMENT_REJECTED, [
        'status_reason' => $reason,
        'finance_user_id' => $currentUserId,
        'finance_decision_at' => dr_now(),
    ]);

    if (!$updated) {
        dr_respond_json(500, ['success' => false, 'message' => 'Unable to reject payment.']);
    }

    dr_send_notification(
        $conn,
        $updated,
        'Payment Rejected',
        'Payment for request ' . $requestId . ' was rejected. Reason: ' . $reason
    );

    dr_respond_json(200, ['success' => true, 'request' => $updated]);
}

if ($action === 'mark_ready') {
    $issuedPath = dra_save_upload($_FILES['issued_file'] ?? [], 'IssuedDocuments');
    if ($issuedPath === null) {
        // Auto-generate issued document when manual upload is not provided.
        $issuedPath = dra_generate_issued_document($row);
    }

    $patch = [
        'ready_at' => dr_now(),
        'qr_code_path' => '/UnifiedFileAttachment/IssuedDocuments/QR/qr_' . preg_replace('/[^A-Za-z0-9_-]/', '', $requestId) . '.png',
    ];
    if ($issuedPath !== null && $issuedPath !== '') {
        $patch['issued_file_path'] = $issuedPath;
    }

    $updated = dr_update_stage($conn, $requestId, DR_STAGE_READY_FOR_CLAIM, $patch);
    if (!$updated) {
        dr_respond_json(500, ['success' => false, 'message' => 'Unable to mark request ready.']);
    }

    dr_send_notification(
        $conn,
        $updated,
        'Document Ready for Claim',
        'Your document request ' . $requestId . ' is ready for claiming/printing.'
    );

    dr_respond_json(200, ['success' => true, 'request' => $updated]);
}

if ($action === 'mark_completed') {
    $updated = dr_update_stage($conn, $requestId, DR_STAGE_COMPLETED, [
        'completed_at' => dr_now(),
    ]);

    if (!$updated) {
        dr_respond_json(500, ['success' => false, 'message' => 'Unable to complete request.']);
    }

    dr_send_notification(
        $conn,
        $updated,
        'Document Request Completed',
        'Your document request ' . $requestId . ' has been completed.'
    );

    dr_respond_json(200, ['success' => true, 'request' => $updated]);
}

dr_respond_json(404, ['success' => false, 'message' => 'Unknown action.']);
