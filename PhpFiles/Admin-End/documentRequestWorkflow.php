<?php
declare(strict_types=1);

require_once __DIR__ . '/../General/security.php';
require_once __DIR__ . '/../General/connection.php';
require_once __DIR__ . '/../General/documentRequestWorkflow.php';

requireRoleSession(['SuperAdmin', 'Official', 'Officials', 'Personnel', 'Personnels', 'Admin', 'Employee'], true);

dr_ensure_table($conn);
dr_ensure_general_fees_table($conn);
dr_backfill_missing_issuance_requests($conn, 5000);

$action = strtolower(trim((string)($_REQUEST['action'] ?? '')));
if ($action === '') {
    dr_respond_json(400, ['success' => false, 'message' => 'Missing action.']);
}

$currentUserId = (string)($_SESSION['user_id'] ?? '');

function dra_strip_legacy_base(string $publicPath): string {
    $publicPath = trim($publicPath);
    $base = rtrim((string)appRootPath(), '/');
    if ($base !== '' && strpos($publicPath, $base) === 0) {
        return substr($publicPath, strlen($base));
    }
    $projectRoot = realpath(__DIR__ . '/../../');
    $projectBase = $projectRoot ? trim((string)basename($projectRoot)) : '';
    if ($projectBase !== '' && strpos($publicPath, '/' . $projectBase) === 0) {
        return substr($publicPath, strlen('/' . $projectBase));
    }
    return $publicPath;
}

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

function dra_strip_area_from_address(string $address): string {
    $value = trim($address);
    if ($value === '') {
        return '';
    }

    // Remove ", Area X" or "Area X," fragments while keeping the rest intact.
    $value = preg_replace('/\s*,\s*Area\s+[A-Za-z0-9-]+\s*(?=,|$)/i', '', $value) ?? $value;
    $value = preg_replace('/(^|,\s*)Area\s+[A-Za-z0-9-]+\s*,\s*/i', '$1', $value) ?? $value;
    $value = preg_replace('/\s{2,}/', ' ', $value) ?? $value;
    $value = trim($value, " \t\n\r\0\x0B,");

    return $value;
}

function dra_public_base_url(): string {
    return appBaseUrl();
}

function dra_qr_verify_url(string $requestId, string $verificationCode): string {
    $vc = $verificationCode !== '' ? $verificationCode : $requestId;
    return rtrim(dra_public_base_url(), '/')
        . '/Guest-End/TransactionInformation.html?request_id='
        . rawurlencode($requestId)
        . '&vc=' . rawurlencode($vc);
}

function dra_humanize_document_type(string $docType): string {
    $text = trim($docType);
    if ($text === '') {
        return 'Document';
    }
    $text = preg_replace('/([a-z])([A-Z])/', '$1 $2', $text) ?? $text;
    $text = str_replace(['_', '-'], ' ', $text);
    $text = preg_replace('/\s+/', ' ', $text) ?? $text;
    return trim($text);
}

function dra_request_notice(array $requestRow, string $requestId, string $suffix): string {
    $docType = dra_humanize_document_type((string)($requestRow['document_type'] ?? ''));
    $rid = trim($requestId) !== '' ? trim($requestId) : trim((string)($requestRow['request_id'] ?? ''));
    if ($rid === '') {
        return 'Your ' . $docType . ' Request has been ' . $suffix;
    }
    return 'Your ' . $docType . ' Request #' . $rid . ' has been ' . $suffix;
}

function dra_decode_request_payload(array $requestRow): array {
    $raw = (string)($requestRow['request_details'] ?? $requestRow['payload_json'] ?? '{}');
    $payload = json_decode($raw, true);
    return is_array($payload) ? $payload : [];
}

function dra_apply_preview_edits(mysqli $conn, string $requestId, array &$requestRow, array $edited): void {
    if ($requestId === '' || empty($edited)) {
        return;
    }

    $payload = dra_decode_request_payload($requestRow);

    $purpose = trim((string)($edited['purpose'] ?? ''));
    $requestOfficer = trim((string)($edited['requestOfficer'] ?? ''));
    $businessName = trim((string)($edited['businessName'] ?? ''));
    $fullAddress = trim((string)($edited['fullAddress'] ?? ''));

    if ($purpose !== '') {
        $payload['request_purpose'] = $purpose;
        $payload['purpose'] = $purpose;
        if (dr_column_exists($conn, 'documentrequesttbl', 'purpose')) {
            $stmtPurpose = $conn->prepare("UPDATE documentrequesttbl SET purpose = ? WHERE request_id = ? LIMIT 1");
            if ($stmtPurpose) {
                $stmtPurpose->bind_param('ss', $purpose, $requestId);
                $stmtPurpose->execute();
                $stmtPurpose->close();
            }
        }
        $requestRow['purpose'] = $purpose;
    }
    if ($requestOfficer !== '') {
        $payload['request_officer'] = $requestOfficer;
    }
    if ($businessName !== '') {
        $payload['business_name'] = $businessName;
    }
    if ($fullAddress !== '') {
        $payload['full_address'] = $fullAddress;
    }

    $encoded = dr_safe_json($payload);
    if (dr_column_exists($conn, 'documentrequesttbl', 'request_details')) {
        $stmt = $conn->prepare("UPDATE documentrequesttbl SET request_details = ? WHERE request_id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('ss', $encoded, $requestId);
            $stmt->execute();
            $stmt->close();
            $requestRow['request_details'] = $encoded;
        }
    }
}

function dra_generate_issued_document(array $requestRow): ?string {
    if (!class_exists('FPDF')) {
        $fpdfPaths = [
            __DIR__ . '/../../composer-email-handler/vendor/autoload.php',
            __DIR__ . '/../../vendor/autoload.php',
        ];
        foreach ($fpdfPaths as $autoloadPath) {
            if (is_file($autoloadPath)) {
                require_once $autoloadPath;
                if (class_exists('FPDF')) {
                    break;
                }
            }
        }
    }
    if (!class_exists('FPDF')) {
        return null;
    }

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
    $payload = dra_decode_request_payload($requestRow);

    $fullName = dra_format_full_name_from_payload($payload);
    if ($fullName === '') {
        $fullName = trim((string)($requestRow['resident_id'] ?? 'Resident'));
    }
    $address = trim((string)($payload['full_address'] ?? 'Barangay San Jose, Rodriguez, Rizal'));
    $address = dra_strip_area_from_address($address);
    if ($address === '') {
        $address = 'Barangay San Jose, Rodriguez, Rizal';
    }
    $certNo = trim((string)($requestRow['certificate_number'] ?? ''));
    $orNo = trim((string)($requestRow['or_number'] ?? ''));

    $verificationCode = trim((string)($requestRow['verification_code'] ?? ''));
    $verifyUrl = dra_qr_verify_url($requestId, $verificationCode);

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
    $rightLogo = $baseDir . '/Images/Montalban_Logo.png';
    if (is_file($rightLogo)) {
        $pdf->Image($rightLogo, 168, 14, 26, 26);
    }
    $docTypeNorm = strtolower(trim($docType));
    $isIndigency = strpos($docTypeNorm, 'indigency') !== false;
    $fontFace = 'Arial';

    $pdf->SetFont($fontFace, 'B', 11);
    $pdf->Cell(0, 5, 'REPUBLIKA NG PILIPINAS', 0, 1, 'C');
    $pdf->SetFont($fontFace, '', 10);
    $pdf->Cell(0, 5, 'LALAWIGAN NG RIZAL', 0, 1, 'C');
    $pdf->Cell(0, 5, 'BAYAN NG RODRIGUEZ', 0, 1, 'C');
    $pdf->SetFont($fontFace, 'B', 16);
    $pdf->Cell(0, 7, 'BARANGAY SAN JOSE', 0, 1, 'C');
    if ($isIndigency) {
        $pdf->Ln(2);
        $pdf->Line(18, $pdf->GetY(), 192, $pdf->GetY());
        $pdf->Ln(8);
        $pdf->SetFont('Arial', 'B', 17);
        $pdf->Cell(0, 8, 'TANGGAPAN NG PUNONG BARANGAY', 0, 1, 'C');
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 7, 'CERTIFICATE OF INDIGENCY', 0, 1, 'C');
        $pdf->Ln(6);
    } else {
        $pdf->SetFont($fontFace, 'B', 12);
        $pdf->Cell(0, 6, 'TANGGAPAN NG PUNONG BARANGAY', 0, 1, 'C');
        $pdf->Ln(3);
        $pdf->Line(18, $pdf->GetY(), 192, $pdf->GetY());
        $pdf->Ln(8);
        $pdf->SetFont($fontFace, 'B', 14);
        $pdf->Cell(0, 8, strtoupper($docType), 0, 1, 'C');
        $pdf->Ln(6);
    }

    $pdf->SetFont($fontFace, '', 12);
    if ($isIndigency) {
        if (is_file($qrDiskPath)) {
            // Place QR beside the bottom disclaimer, in the right-side free space.
            $pdf->Image($qrDiskPath, 166, 254, 20, 20);
        }
        $requestOfficer = trim((string)($payload['request_officer'] ?? ''));
        $requestPurpose = trim((string)($payload['request_purpose'] ?? $purpose));
        if ($requestPurpose === '') {
            $requestPurpose = 'PURPOSE';
        }
        $issuedDateObj = new DateTime();
        $day = (int)$issuedDateObj->format('j');
        $monthUpper = strtoupper($issuedDateObj->format('F'));
        $yearNum = $issuedDateObj->format('Y');
        $v = $day % 100;
        $suffix = ($v >= 11 && $v <= 13) ? 'th' : (($day % 10 === 1) ? 'st' : (($day % 10 === 2) ? 'nd' : (($day % 10 === 3) ? 'rd' : 'th')));
        $issuedAsDocx = $day . $suffix . ' day of ' . $monthUpper . ' ' . $yearNum;

        $pdf->SetFont($fontFace, 'B', 12);
        $pdf->SetXY(22, 78);
        $pdf->Cell(14, 7, 'TO', 0, 0, 'L');
        $pdf->Cell(4, 7, ':', 0, 0, 'C');
        if ($requestOfficer === '') {
            $pdf->Line(39, 84, 106, 84);
            $pdf->Line(39, 90, 106, 90);
            $pdf->Line(39, 96, 106, 96);
            $pdf->SetY(96);
        } else {
            $offLines = preg_split("/\r\n|\n|\r/", $requestOfficer);
            $firstLine = trim((string)($offLines[0] ?? ''));
            $pdf->SetFont($fontFace, 'B', 11);
            $pdf->Cell(0, 7, strtoupper($firstLine), 0, 1, 'L');
            $pdf->SetX(39);
            for ($i = 1; $i < count($offLines); $i++) {
                $line = trim((string)$offLines[$i]);
                if ($line === '') continue;
                $pdf->Cell(0, 7, strtoupper($line), 0, 1, 'L');
                $pdf->SetX(39);
            }
        }
        $pdf->Ln(7);
        $pdf->SetFont($fontFace, '', 12);
        $pdf->SetX(22);
        $pdf->MultiCell(0, 7, 'This is to certify that ' . $fullName . ', resident of ' . $address . ' belongs to the one of the indigent families of this Barangay. The Income of this family is barely enough to meet their day-to-day needs.', 0, 'J');
        $pdf->Ln(4);
        $pdf->SetX(22);
        $pdf->MultiCell(0, 7, 'This certification is being issued upon the request of the above subject in person in connection with his/her application for ' . $requestPurpose . ' purposes only.', 0, 'J');
        $pdf->Ln(4);
        $pdf->SetX(22);
        $pdf->MultiCell(0, 7, 'Issued this ' . $issuedAsDocx . ', at the office of the punong Barangay, Barangay San Jose, Rodriguez (Montalban), Rizal.', 0, 'J');

        // Issued by block (lower-left)
        $pdf->SetFont($fontFace, '', 11);
        $pdf->SetXY(22, 178);
        $pdf->Cell(0, 6, 'Issued by: MINERVA D. QUITA', 0, 1, 'L');
        $pdf->SetX(39);
        $pdf->SetFont($fontFace, 'I', 11);
        $pdf->Cell(0, 6, 'Barangay Secretary', 0, 1, 'L');

        // Punong Barangay signature block (lower-right)
        $pdf->Line(112, 181, 168, 181);
        $pdf->SetFont($fontFace, 'B', 11);
        $pdf->SetXY(112, 183);
        $pdf->Cell(56, 6, 'HON. GLENN S. EVANGELISTA', 0, 1, 'C');
        $pdf->SetFont($fontFace, 'I', 11);
        $pdf->SetX(112);
        $pdf->Cell(56, 6, 'Punong Barangay', 0, 1, 'C');

        // Footer note in a centered constrained width area.
        $pdf->SetFont($fontFace, 'I', 8);
        $pdf->SetXY(46, 258);
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->MultiCell(118, 4, "This certificate is valid for Forty-five (45) days from the date of issue, check the\nQR code to verify the authenticity of this document.", 0, 'C');

        $pdf->Output('F', $diskPath);
        return '/UnifiedFileAttachment/IssuedDocuments/Generated/' . $fileName;
    } else {
        if (is_file($qrDiskPath)) {
            $pdf->Image($qrDiskPath, 166, 238, 26, 26);
        }
        $pdf->MultiCell(0, 7, 'This is to certify that ' . $fullName . ' is a bona fide resident of ' . $address . '.', 0, 'J');
        $pdf->Ln(2);
        $pdf->MultiCell(0, 7, 'This certification is issued upon request for ' . ($purpose !== '' ? $purpose : 'legal purpose') . '.', 0, 'J');
        $pdf->Ln(2);
        $pdf->MultiCell(0, 7, 'Issued this ' . $issuedAt . ' at Barangay San Jose, Rodriguez, Rizal.', 0, 'J');

        $pdf->Ln(10);
        $pdf->SetFont($fontFace, '', 10);
        $pdf->Cell(0, 6, 'Request ID: ' . $requestId, 0, 1, 'L');
        if ($certNo !== '') {
            $pdf->Cell(0, 6, 'Certificate No: ' . $certNo, 0, 1, 'L');
        }
        if ($orNo !== '') {
            $pdf->Cell(0, 6, 'OR No: ' . $orNo, 0, 1, 'L');
        }
        $pdf->Cell(0, 6, 'Verify via QR or: ' . $verifyUrl, 0, 1, 'L');
    }

    $pdf->SetY(250);
    $pdf->Line(18, 250, 88, 250);
    $pdf->SetFont($fontFace, 'B', 11);
    $pdf->Cell(70, 7, 'HON. GLENN S. EVANGELISTA', 0, 1, 'L');
    $pdf->SetFont($fontFace, '', 11);
    $pdf->Cell(70, 6, 'Punong Barangay', 0, 1, 'L');

    $pdf->Output('F', $diskPath);

    return '/UnifiedFileAttachment/IssuedDocuments/Generated/' . $fileName;
}

function dra_generate_issued_document_safe(array $requestRow): ?string {
    $bufferLevel = ob_get_level();
    ob_start();
    try {
        $path = dra_generate_issued_document($requestRow);
    } catch (Throwable $e) {
        error_log('[dra_generate_issued_document_safe] ' . $e->getMessage());
        $path = null;
    } finally {
        while (ob_get_level() > $bufferLevel) {
            ob_end_clean();
        }
    }
    return $path;
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

function dra_resident_profile_snapshot(mysqli $conn, string $residentUserId, string $residentId): array {
    static $cache = [];

    $cacheKey = trim($residentUserId) . '|' . trim($residentId);
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $empty = [
        'resident_id' => trim($residentId),
        'resident_user_id' => trim($residentUserId),
        'last_name' => '',
        'first_name' => '',
        'middle_name' => '',
        'suffix' => '',
        'birthdate' => '',
        'age' => '',
        'sex' => '',
        'civil_status' => '',
        'religion' => '',
        'occupation' => '',
        'contact_number' => '',
        'full_address' => '',
        'proof_residency_path' => '',
        'proof_residency_name' => '',
        'proof_residency_type' => '',
        'proof_residency_id_number' => '',
    ];

    $sql = "
        SELECT
            r.resident_id,
            r.user_id,
            r.lastname,
            r.firstname,
            r.middlename,
            r.suffix,
            r.birthdate,
            r.sex,
            r.civil_status,
            r.religion,
            r.occupation,
            r.occupation_detail,
            u.phone_number,
            (
                SELECT uf.file_path
                FROM unifiedfileattachmenttbl uf
                LEFT JOIN documenttypelookuptbl dt
                    ON dt.document_type_id = uf.document_type_id
                WHERE uf.source_type = 'ResidentProfiling'
                  AND uf.source_id = r.resident_id
                  AND (uf.remarks IS NULL OR uf.remarks NOT LIKE 'sector:%')
                  AND LOWER(COALESCE(dt.document_type_name, '')) <> '2x2 picture'
                ORDER BY uf.upload_timestamp DESC, uf.attachment_id DESC
                LIMIT 1
            ) AS proof_residency_path,
            (
                SELECT uf.file_name
                FROM unifiedfileattachmenttbl uf
                LEFT JOIN documenttypelookuptbl dt
                    ON dt.document_type_id = uf.document_type_id
                WHERE uf.source_type = 'ResidentProfiling'
                  AND uf.source_id = r.resident_id
                  AND (uf.remarks IS NULL OR uf.remarks NOT LIKE 'sector:%')
                  AND LOWER(COALESCE(dt.document_type_name, '')) <> '2x2 picture'
                ORDER BY uf.upload_timestamp DESC, uf.attachment_id DESC
                LIMIT 1
            ) AS proof_residency_name,
            (
                SELECT dt.document_type_name
                FROM unifiedfileattachmenttbl uf
                LEFT JOIN documenttypelookuptbl dt
                    ON dt.document_type_id = uf.document_type_id
                WHERE uf.source_type = 'ResidentProfiling'
                  AND uf.source_id = r.resident_id
                  AND (uf.remarks IS NULL OR uf.remarks NOT LIKE 'sector:%')
                  AND LOWER(COALESCE(dt.document_type_name, '')) <> '2x2 picture'
                ORDER BY uf.upload_timestamp DESC, uf.attachment_id DESC
                LIMIT 1
            ) AS proof_residency_type,
            (
                SELECT uf.id_number
                FROM unifiedfileattachmenttbl uf
                LEFT JOIN documenttypelookuptbl dt
                    ON dt.document_type_id = uf.document_type_id
                WHERE uf.source_type = 'ResidentProfiling'
                  AND uf.source_id = r.resident_id
                  AND (uf.remarks IS NULL OR uf.remarks NOT LIKE 'sector:%')
                  AND LOWER(COALESCE(dt.document_type_name, '')) <> '2x2 picture'
                ORDER BY uf.upload_timestamp DESC, uf.attachment_id DESC
                LIMIT 1
            ) AS proof_residency_id_number,
            a.unit_number,
            a.street_number,
            a.street_name,
            a.phase_number,
            a.subdivision,
            a.area_number
        FROM residentinformationtbl r
        LEFT JOIN useraccountstbl u ON u.user_id = r.user_id
        LEFT JOIN residentaddresstbl a
            ON a.address_id = (
                SELECT a2.address_id
                FROM residentaddresstbl a2
                WHERE a2.resident_id = r.resident_id
                ORDER BY a2.address_id DESC
                LIMIT 1
            )
        WHERE (r.user_id = ? AND ? <> '')
           OR (r.resident_id = ? AND ? <> '')
        ORDER BY r.resident_id DESC
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $cache[$cacheKey] = $empty;
        return $empty;
    }

    $stmt->bind_param('ssss', $residentUserId, $residentUserId, $residentId, $residentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    if (!$row) {
        $cache[$cacheKey] = $empty;
        return $empty;
    }

    $birthdate = trim((string)($row['birthdate'] ?? ''));
    $age = '';
    if ($birthdate !== '') {
        try {
            $dob = new DateTime($birthdate);
            $age = (string)((new DateTime())->diff($dob)->y);
        } catch (Throwable $e) {
            $age = '';
        }
    }

    $unit = trim((string)($row['unit_number'] ?? ''));
    $streetNumber = trim((string)($row['street_number'] ?? ''));
    $streetName = trim((string)($row['street_name'] ?? ''));
    $phase = trim((string)($row['phase_number'] ?? ''));
    $subdivision = trim((string)($row['subdivision'] ?? ''));
    $area = trim((string)($row['area_number'] ?? ''));
    $areaNormalized = trim((string)(preg_replace('/^area\b\.?\s*/i', '', $area) ?? $area));
    $fullAddressParts = [];
    if ($unit !== '') $fullAddressParts[] = 'Unit ' . $unit;
    $streetLine = trim($streetNumber . ' ' . $streetName);
    if ($streetLine !== '') $fullAddressParts[] = $streetLine;
    if ($phase !== '') $fullAddressParts[] = 'Phase ' . $phase;
    if ($subdivision !== '') $fullAddressParts[] = $subdivision . ' Subdivision';
    if ($areaNormalized !== '') $fullAddressParts[] = 'Area ' . $areaNormalized;
    $fullAddressParts[] = 'San Jose';
    $fullAddressParts[] = 'Rodriguez';
    $fullAddressParts[] = 'Rizal';
    $fullAddress = implode(', ', array_values(array_filter($fullAddressParts, static fn($v) => trim((string)$v) !== '')));

    $occupationDetail = trim((string)($row['occupation_detail'] ?? ''));
    $occupation = ((int)($row['occupation'] ?? 0) === 1)
        ? ($occupationDetail !== '' ? $occupationDetail : 'Employed')
        : 'Unemployed';

    $profile = [
        'resident_id' => (string)($row['resident_id'] ?? ''),
        'resident_user_id' => (string)($row['user_id'] ?? ''),
        'last_name' => (string)($row['lastname'] ?? ''),
        'first_name' => (string)($row['firstname'] ?? ''),
        'middle_name' => (string)($row['middlename'] ?? ''),
        'suffix' => (string)($row['suffix'] ?? ''),
        'birthdate' => $birthdate,
        'age' => $age,
        'sex' => (string)($row['sex'] ?? ''),
        'civil_status' => (string)($row['civil_status'] ?? ''),
        'religion' => (string)($row['religion'] ?? ''),
        'occupation' => $occupation,
        'contact_number' => (string)($row['phone_number'] ?? ''),
        'full_address' => $fullAddress,
        'proof_residency_path' => (string)($row['proof_residency_path'] ?? ''),
        'proof_residency_name' => (string)($row['proof_residency_name'] ?? ''),
        'proof_residency_type' => (string)($row['proof_residency_type'] ?? ''),
        'proof_residency_id_number' => (string)($row['proof_residency_id_number'] ?? ''),
    ];

    $cache[$cacheKey] = $profile;
    return $profile;
}

dra_backfill_payment_verified_to_ready($conn);

if ($action === 'list') {
    $where = [];
    $types = '';
    $vals = [];

    $stageCol = dr_column_exists($conn, 'documentrequesttbl', 'stage') ? 'stage' : null;
    $stage = trim((string)($_GET['stage'] ?? ''));
    if ($stage !== '') {
        if ($stageCol !== null) {
            $where[] = $stageCol . ' = ?';
            $types .= 's';
            $vals[] = $stage;
        } else {
            $statusId = dr_find_request_status_id_by_stage($conn, $stage);
            $statusCol = dr_request_status_column($conn);
            if ($statusId !== null && $statusCol !== null) {
                $where[] = $statusCol . ' = ?';
                $types .= 'i';
                $vals[] = $statusId;
            }
        }
    }

    $search = trim((string)($_GET['q'] ?? ''));
    if ($search !== '') {
        $parts = ['request_id LIKE ?'];
        $types .= 's';
        $vals[] = '%' . $search . '%';
        if (dr_column_exists($conn, 'documentrequesttbl', 'resident_id')) {
            $parts[] = 'resident_id LIKE ?';
            $types .= 's';
            $vals[] = '%' . $search . '%';
        }
        $parts[] = 'request_details LIKE ?';
        $types .= 's';
        $vals[] = '%' . $search . '%';
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }

    $sql = 'SELECT * FROM documentrequesttbl';
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $orderCol = dr_column_exists($conn, 'documentrequesttbl', 'submitted_at') ? 'submitted_at' : 'request_timestamp';
    $sql .= ' ORDER BY ' . $orderCol . ' DESC, request_id DESC';

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
        dr_hydrate_request_derived_fields($conn, $row);
        dr_merge_finance_transaction_into_request($conn, $row);
        dr_sync_stage_from_status_lookup($conn, $row);
        $row['stage_label'] = dr_stage_label((string)$row['stage']);
        $row['fee_amount'] = dr_get_fee_amount_for_document_type($conn, (string)($row['document_type'] ?? ''));
        $payload = json_decode((string)($row['request_details'] ?? $row['payload_json'] ?? '{}'), true);
        $row['payload'] = is_array($payload) ? $payload : [];
        $row['resident_profile'] = dra_resident_profile_snapshot(
            $conn,
            (string)($row['resident_user_id'] ?? ''),
            (string)($row['resident_id'] ?? '')
        );
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
    $relative = '/' . ltrim(dra_strip_legacy_base($publicPath), '/');
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

if ($action === 'view_issued') {
    if ($requestId === '') {
        http_response_code(422);
        exit('Missing request ID.');
    }
    $row = dr_fetch_request($conn, $requestId);
    if (!$row) {
        http_response_code(404);
        exit('Request not found.');
    }
    $stage = strtolower(trim((string)($row['stage'] ?? '')));
    if ($stage !== DR_STAGE_COMPLETED) {
        http_response_code(422);
        exit('Issued document can only be viewed after completion.');
    }
    $publicPath = trim((string)($row['issued_file_path'] ?? ''));
    if ($publicPath === '') {
        http_response_code(404);
        exit('Issued document not found.');
    }

    $baseDir = realpath(__DIR__ . '/../../');
    if ($baseDir === false) {
        http_response_code(500);
        exit('Path resolution failed.');
    }
    $relative = '/' . ltrim(dra_strip_legacy_base($publicPath), '/');
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
    $editedPreview = [];
    $editedPreviewRaw = trim((string)($_POST['edited_preview'] ?? ''));
    if ($editedPreviewRaw !== '') {
        $decoded = json_decode($editedPreviewRaw, true);
        if (is_array($decoded)) {
            $editedPreview = $decoded;
        }
    }
    if (!empty($editedPreview)) {
        dra_apply_preview_edits($conn, $requestId, $row, $editedPreview);
        $row = dr_fetch_request($conn, $requestId) ?? $row;
    }

    $defaultFee = dr_get_fee_amount_for_document_type($conn, (string)($row['document_type'] ?? ''));
    $isFreeDocument = ($defaultFee !== null && (float)$defaultFee <= 0.0);
    $nextStage = $isFreeDocument ? DR_STAGE_READY_FOR_CLAIM : DR_STAGE_FOR_PAYMENT;
    $patch = [
        'status_reason' => null,
        'personnel_user_id' => $currentUserId,
        'personnel_decision_at' => dr_now(),
        'fee_amount' => $defaultFee,
    ];

    if ($isFreeDocument) {
        $verificationCode = trim((string)($row['verification_code'] ?? ''));
        if ($verificationCode === '') {
            $verificationCode = strtoupper(bin2hex(random_bytes(8)));
        }
        $qrCodePath = '/UnifiedFileAttachment/IssuedDocuments/QR/qr_' . preg_replace('/[^A-Za-z0-9_-]/', '', $requestId) . '.png';
        $issuedPath = trim((string)($row['issued_file_path'] ?? ''));
        if ($issuedPath === '') {
            $issuedPath = (string)(dra_generate_issued_document_safe(array_merge((array)$row, [
                'verification_code' => $verificationCode,
            ])) ?? '');
        }
        $patch['verification_code'] = $verificationCode;
        $patch['qr_code_path'] = $qrCodePath;
        $patch['ready_at'] = dr_now();
        if ($issuedPath !== '') {
            $patch['issued_file_path'] = $issuedPath;
        }
    }

    $updated = dr_update_stage($conn, $requestId, $nextStage, $patch);
    if (!$updated) {
        dr_respond_json(500, ['success' => false, 'message' => 'Unable to approve request.']);
    }

    dr_send_notification(
        $conn,
        $updated,
        $isFreeDocument ? 'Document Request Approved for Release' : 'Document Request Approved for Payment',
        $isFreeDocument
            ? dra_request_notice($updated, $requestId, 'approved and is now for release.')
            : dra_request_notice($updated, $requestId, 'approved and is now waiting for payment.')
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
        dra_request_notice($updated, $requestId, 'rejected. Reason: ' . $reason)
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
        dra_request_notice($updated, $requestId, 'payment verified. OR: ' . $orNumber . '. Certificate no: ' . $certificateNumber . '. The document is now for release.')
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
        dra_request_notice($updated, $requestId, 'payment rejected. Reason: ' . $reason)
    );

    dr_respond_json(200, ['success' => true, 'request' => $updated]);
}

if ($action === 'mark_ready') {
    $verificationCode = trim((string)($row['verification_code'] ?? ''));
    if ($verificationCode === '') {
        $verificationCode = strtoupper(bin2hex(random_bytes(8)));
    }
    $issuedPath = dra_save_upload($_FILES['issued_file'] ?? [], 'IssuedDocuments');
    if ($issuedPath === null) {
        // Auto-generate issued document when manual upload is not provided.
        $issuedPath = dra_generate_issued_document(array_merge((array)$row, [
            'verification_code' => $verificationCode,
        ]));
    }

    $patch = [
        'ready_at' => dr_now(),
        'verification_code' => $verificationCode,
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
        dra_request_notice($updated, $requestId, 'prepared and is now for release.')
    );

    dr_respond_json(200, ['success' => true, 'request' => $updated]);
}

if ($action === 'mark_completed') {
    $patch = [
        'completed_at' => dr_now(),
    ];
    $issuedPath = trim((string)($row['issued_file_path'] ?? ''));
    if ($issuedPath === '') {
        $verificationCode = trim((string)($row['verification_code'] ?? ''));
        if ($verificationCode === '') {
            $verificationCode = strtoupper(bin2hex(random_bytes(8)));
        }
        $generated = dra_generate_issued_document_safe(array_merge((array)$row, [
            'verification_code' => $verificationCode,
        ]));
        if (!empty($generated)) {
            $patch['issued_file_path'] = $generated;
            $patch['verification_code'] = $verificationCode;
            $patch['qr_code_path'] = '/UnifiedFileAttachment/IssuedDocuments/QR/qr_' . preg_replace('/[^A-Za-z0-9_-]/', '', $requestId) . '.png';
        }
    }

    $updated = dr_update_stage($conn, $requestId, DR_STAGE_COMPLETED, $patch);

    if (!$updated) {
        dr_respond_json(500, ['success' => false, 'message' => 'Unable to complete request.']);
    }

    dr_send_notification(
        $conn,
        $updated,
        'Document Request Completed',
        dra_request_notice($updated, $requestId, 'completed and released.')
    );

    dr_respond_json(200, ['success' => true, 'request' => $updated]);
}

dr_respond_json(404, ['success' => false, 'message' => 'Unknown action.']);
