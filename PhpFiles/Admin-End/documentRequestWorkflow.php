<?php
declare(strict_types=1);

require_once __DIR__ . '/../General/security.php';
require_once __DIR__ . '/../General/connection.php';
require_once __DIR__ . '/../General/documentRequestWorkflow.php';

requireRoleSession(['SuperAdmin', 'Official', 'Officials', 'Personnel', 'Personnels', 'Admin', 'Employee'], true);

$action = strtolower(trim((string)($_REQUEST['action'] ?? '')));
if ($action === '') {
    dr_respond_json(400, ['success' => false, 'message' => 'Missing action.']);
}

// NOTE: avoid expensive schema/backfill maintenance on hot request paths.
// Run maintenance manually when needed:
//   /PhpFiles/Admin-End/documentRequestWorkflow.php?action=maintenance_run
if ($action === 'maintenance_run') {
    dr_ensure_table($conn);
    dr_ensure_general_fees_table($conn);
    $syncedFinance = dr_backfill_missing_finance_transactions($conn, 2000);
    $prunedFree = dr_prune_free_document_finance_transactions($conn, 5000);
    $syncedIssuance = dr_backfill_missing_issuance_requests($conn, 5000);
    dr_respond_json(200, [
        'success' => true,
        'maintenance' => [
            'finance_backfilled' => $syncedFinance,
            'free_pruned' => $prunedFree,
            'issuance_backfilled' => $syncedIssuance,
        ],
    ]);
}

if ($action === 'optimize_indexes') {
    dra_ensure_list_hotpath_indexes($conn);
    dr_respond_json(200, ['success' => true, 'message' => 'List indexes checked/applied.']);
}

$currentUserId = (string)($_SESSION['user_id'] ?? '');

function dra_is_finance_user(mysqli $conn, string $userId): bool {
    $userId = trim($userId);
    if ($userId === '' || !dr_table_exists($conn, 'officialinformationtbl')) {
        return false;
    }
    $hasPositionAccess = dr_column_exists($conn, 'officialinformationtbl', 'position_access');
    $sql = $hasPositionAccess
        ? "SELECT role_access, position_access, department FROM officialinformationtbl WHERE user_id = ? LIMIT 1"
        : "SELECT role_access, NULL AS position_access, department FROM officialinformationtbl WHERE user_id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return false;
    }

    $role = strtolower(trim((string)($row['role_access'] ?? '')));
    $position = strtolower(trim((string)($row['position_access'] ?? '')));
    $department = strtolower(trim((string)($row['department'] ?? '')));

    return (
        strpos($department, 'finance') !== false
        || strpos($position, 'cashier') !== false
        || strpos($position, 'finance') !== false
        || ($role === 'employee' && strpos($department, 'finance') !== false)
    );
}

function dra_send_notification_deferred(mysqli $conn, array $request, string $subject, string $message): void {
    register_shutdown_function(static function () use ($conn, $request, $subject, $message): void {
        // Flush response first when supported, then send notifications outside request hot path.
        if (function_exists('session_write_close')) {
            @session_write_close();
        }
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }
        try {
            dr_send_notification($conn, $request, $subject, $message);
        } catch (Throwable $e) {
            error_log('[documentRequestWorkflow][notification] deferred send failed: ' . $e->getMessage());
        }
    });
}

function dra_ensure_list_hotpath_indexes(mysqli $conn): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    if (dr_table_exists($conn, 'documentrequesttbl')) {
        $idxNames = [];
        $res = $conn->query("SHOW INDEX FROM documentrequesttbl");
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $idxNames[(string)($row['Key_name'] ?? '')] = true;
            }
        }
        if (dr_column_exists($conn, 'documentrequesttbl', 'submitted_at') && !isset($idxNames['idx_docreq_submitted_at'])) {
            $conn->query("ALTER TABLE documentrequesttbl ADD INDEX idx_docreq_submitted_at (submitted_at)");
        }
        if (dr_column_exists($conn, 'documentrequesttbl', 'stage')
            && dr_column_exists($conn, 'documentrequesttbl', 'submitted_at')
            && !isset($idxNames['idx_docreq_stage_submitted_at'])) {
            $conn->query("ALTER TABLE documentrequesttbl ADD INDEX idx_docreq_stage_submitted_at (stage, submitted_at)");
        }
        if (dr_column_exists($conn, 'documentrequesttbl', 'request_timestamp') && !isset($idxNames['idx_docreq_request_timestamp'])) {
            $conn->query("ALTER TABLE documentrequesttbl ADD INDEX idx_docreq_request_timestamp (request_timestamp)");
        }
        if (dr_column_exists($conn, 'documentrequesttbl', 'stage')
            && dr_column_exists($conn, 'documentrequesttbl', 'request_timestamp')
            && !isset($idxNames['idx_docreq_stage_request_timestamp'])) {
            $conn->query("ALTER TABLE documentrequesttbl ADD INDEX idx_docreq_stage_request_timestamp (stage, request_timestamp)");
        }
    }

    if (dr_table_exists($conn, 'financetransactiontbl')) {
        $hasRequestIndex = false;
        $res = $conn->query("SHOW INDEX FROM financetransactiontbl");
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                if (strcasecmp((string)($row['Column_name'] ?? ''), 'request_id') === 0) {
                    $hasRequestIndex = true;
                    break;
                }
            }
        }
        if (!$hasRequestIndex && dr_column_exists($conn, 'financetransactiontbl', 'request_id')) {
            $conn->query("ALTER TABLE financetransactiontbl ADD INDEX idx_transaction_request_id (request_id)");
        }
    }
}

function dra_get_fee_map_for_document_types(mysqli $conn, array $documentTypes): array {
    $out = [];
    $names = [];
    foreach ($documentTypes as $docType) {
        $doc = trim((string)$docType);
        if ($doc === '' || !dr_is_issuance_document_type($doc)) {
            continue;
        }
        $names[$doc] = true;
    }
    if (!$names || !dr_table_exists($conn, 'documenttypelookuptbl') || !dr_table_exists($conn, 'generalfeestbl')) {
        return $out;
    }

    $nameList = array_keys($names);
    $placeholders = implode(',', array_fill(0, count($nameList), '?'));
    $sql = "
        SELECT dt.document_type_name, gf.amount
        FROM documenttypelookuptbl dt
        LEFT JOIN generalfeestbl gf ON gf.document_type_id = dt.document_type_id
        WHERE dt.document_type_name IN ($placeholders)
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return $out;
    }
    $types = str_repeat('s', count($nameList));
    $bindArgs = [$types];
    foreach ($nameList as $i => $value) {
        $bindArgs[] = &$nameList[$i];
    }
    call_user_func_array([$stmt, 'bind_param'], $bindArgs);
    $stmt->execute();
    $rs = $stmt->get_result();
    while ($row = $rs->fetch_assoc()) {
        $name = trim((string)($row['document_type_name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $amount = $row['amount'];
        $out[$name] = ($amount === null) ? null : (float)$amount;
    }
    $stmt->close();
    return $out;
}

function dra_select_or_null(mysqli $conn, string $table, string $column, string $alias): string {
    if (dr_column_exists($conn, $table, $column)) {
        return "d.{$column} AS {$alias}";
    }
    return "NULL AS {$alias}";
}

function dra_strip_legacy_base(string $publicPath): string {
    $publicPath = trim($publicPath);
    if ($publicPath === '') {
        return '';
    }

    // Normalize slashes to support Windows-style stored paths.
    $publicPath = str_replace('\\', '/', $publicPath);

    // If a full URL is stored, keep only its path portion.
    if (preg_match('/^https?:\/\//i', $publicPath)) {
        $urlPath = parse_url($publicPath, PHP_URL_PATH);
        if (is_string($urlPath) && $urlPath !== '') {
            $publicPath = $urlPath;
        }
    }

    // If an absolute filesystem path is stored, strip project root prefix.
    $projectRoot = realpath(__DIR__ . '/../../');
    if ($projectRoot !== false) {
        $projectRootNorm = str_replace('\\', '/', rtrim($projectRoot, '/'));
        if (strpos($publicPath, $projectRootNorm) === 0) {
            return substr($publicPath, strlen($projectRootNorm));
        }
    }

    $base = rtrim((string)appRootPath(), '/');
    if ($base !== '' && strpos($publicPath, $base) === 0) {
        return substr($publicPath, strlen($base));
    }
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

    $docTypeNorm = strtolower(trim($docType));
    $isIndigency = strpos($docTypeNorm, 'indigency') !== false;
    $isGoodMoral = (strpos($docTypeNorm, 'goodmoral') !== false) || (strpos($docTypeNorm, 'good moral') !== false);
    $isResidency = strpos($docTypeNorm, 'residency') !== false;

    // Indigency/Good Moral/Residency: prefer .docx template output when template and PhpWord are available.
    if ($isIndigency || $isGoodMoral || $isResidency) {
        if ($isIndigency) {
            $templateFile = 'Certificate of Indigency.docx';
        } elseif ($isGoodMoral) {
            $templateFile = 'Certificate of Good Moral.docx';
        } else {
            $residencyTemplates = ['general certification.docx', 'GeneralCertification.docx'];
            $templateFile = 'GeneralCertification.docx';
            foreach ($residencyTemplates as $candidateTemplate) {
                if (is_file($baseDir . '/Resident-End/Certificates/DocumentIssuance/' . $candidateTemplate)) {
                    $templateFile = $candidateTemplate;
                    break;
                }
            }
        }
        $templatePath = $baseDir . '/Resident-End/Certificates/DocumentIssuance/' . $templateFile;
        if (is_file($templatePath)) {
            if (!class_exists('\PhpOffice\PhpWord\TemplateProcessor')) {
                $phpWordAutoloads = [
                    $baseDir . '/PhpFiles/PhpOffice/vendor/autoload.php',
                    $baseDir . '/vendor/autoload.php',
                ];
                foreach ($phpWordAutoloads as $autoloadPath) {
                    if (is_file($autoloadPath)) {
                        require_once $autoloadPath;
                        if (class_exists('\PhpOffice\PhpWord\TemplateProcessor')) {
                            break;
                        }
                    }
                }
            }

            if (class_exists('\PhpOffice\PhpWord\TemplateProcessor')) {
                try {
                    $template = new \PhpOffice\PhpWord\TemplateProcessor($templatePath);
                    $normalizeText = static function (string $value): string {
                        $v = trim(preg_replace('/\s+/u', ' ', $value));
                        return $v === '' ? '-' : $v;
                    };

                    $requestOfficer = trim((string)($payload['request_officer'] ?? ''));
                    $requestPurpose = trim((string)($payload['request_purpose'] ?? $purpose));
                    if ($isResidency) {
                        $requestPurpose = 'Residency Verification';
                    }
                    if ($requestPurpose === '') {
                        $requestPurpose = $purpose !== '' ? $purpose : 'PURPOSE';
                    }
                    $yearsResidency = trim((string)($payload['years_of_residency'] ?? ''));
                    $monthsResidency = trim((string)($payload['months_of_residency'] ?? ''));
                    $issuedDateObj = new DateTime();
                    $issuedDateWord = $issuedDateObj->format('F j, Y');
                    $day = (int)$issuedDateObj->format('j');
                    $monthUpper = strtoupper($issuedDateObj->format('F'));
                    $yearNum = $issuedDateObj->format('Y');
                    $v = $day % 100;
                    $suffix = ($v >= 11 && $v <= 13) ? 'th' : (($day % 10 === 1) ? 'st' : (($day % 10 === 2) ? 'nd' : (($day % 10 === 3) ? 'rd' : 'th')));
                    $issuedAsDocx = $day . $suffix . ' day of ' . $monthUpper . ' ' . $yearNum;

                    $template->setValue('REQUEST_ID', $normalizeText($requestId));
                    $template->setValue('FULL_NAME', $normalizeText($fullName));
                    $template->setValue('ADDRESS', $normalizeText($address));
                    $template->setValue('PURPOSE', $normalizeText($requestPurpose));
                    $template->setValue('REQUEST_OFFICER', $normalizeText($requestOfficer));
                    $template->setValue('ISSUED_AT', $normalizeText($issuedDateWord));
                    $template->setValue('ISSUED_DATE_WORD', $normalizeText($issuedAsDocx));
                    $template->setValue('CERTIFICATE_NUMBER', $normalizeText($certNo));
                    $template->setValue('OR_NUMBER', $normalizeText($orNo));
                    $template->setValue('VERIFICATION_CODE', $normalizeText($verificationCode));
                    $template->setValue('VERIFY_URL', $normalizeText($verifyUrl));
                    $template->setValue('QR_PUBLIC_PATH', $normalizeText($qrPublicPath));
                    $template->setValue('YEARS_OF_RESIDENCY', $normalizeText($yearsResidency));
                    $template->setValue('MONTHS_OF_RESIDENCY', $normalizeText($monthsResidency));

                    if (is_file($qrDiskPath) && method_exists($template, 'setImageValue')) {
                        $template->setImageValue('QR_IMAGE', [
                            'path' => $qrDiskPath,
                            'width' => 120,
                            'height' => 120,
                            'ratio' => true,
                        ]);
                    }

                    $docxName = 'issued_' . preg_replace('/[^A-Za-z0-9_-]/', '', $requestId) . '_' . date('YmdHis') . '.docx';
                    $docxDiskPath = $outDir . '/' . $docxName;
                    $template->saveAs($docxDiskPath);

                    if (is_file($docxDiskPath) && filesize($docxDiskPath) > 0) {
                        $pdfDiskPath = dra_convert_docx_to_pdf($docxDiskPath, $outDir);
                        if ($pdfDiskPath !== null) {
                            return '/UnifiedFileAttachment/IssuedDocuments/Generated/' . basename($pdfDiskPath);
                        }
                        error_log('[dra_generate_issued_document][docx_template] docx->pdf conversion unavailable/failed; using PDF fallback renderer');
                    }
                } catch (Throwable $e) {
                    error_log('[dra_generate_issued_document][docx_template] ' . $e->getMessage());
                }
            }
        }
    }

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

    $fileName = 'issued_' . preg_replace('/[^A-Za-z0-9_-]/', '', $requestId) . '_' . date('YmdHis') . '.pdf';
    $diskPath = $outDir . '/' . $fileName;

    // Use short bond paper (8.5x11) instead of A4.
    $pdf = new FPDF('P', 'mm', 'Letter');
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
    $isSpecialCertificate = $isIndigency || $isGoodMoral;
    $fontFace = 'Times';
    $indigencyFont = 'Arial';

    $pdf->SetFont($fontFace, 'B', 11);
    $pdf->Cell(0, 5, 'REPUBLIKA NG PILIPINAS', 0, 1, 'C');
    $pdf->SetFont($fontFace, '', 10);
    $pdf->Cell(0, 5, 'LALAWIGAN NG RIZAL', 0, 1, 'C');
    $pdf->Cell(0, 5, 'BAYAN NG RODRIGUEZ', 0, 1, 'C');
    $pdf->Ln(1);
    $pdf->SetFont($fontFace, 'B', 16);
    $pdf->Cell(0, 7, 'BARANGAY SAN JOSE', 0, 1, 'C');
    if ($isSpecialCertificate) {
        $pdf->Ln(2);
        $pdf->Line(18, $pdf->GetY(), 192, $pdf->GetY());
        $pdf->Ln(8);
        if ($isIndigency) {
            $pdf->SetFont($indigencyFont, 'B', 17);
            $pdf->Cell(0, 8, 'TANGGAPAN NG PUNONG BARANGAY', 0, 1, 'C');
            $pdf->SetFont($indigencyFont, 'B', 12);
            $pdf->Cell(0, 7, 'CERTIFICATE OF INDIGENCY', 0, 1, 'C');
            $pdf->Ln(6);
        } else {
            $pdf->SetFont($indigencyFont, 'B', 17);
            $pdf->Cell(0, 8, 'TANGGAPAN NG PUNONG BARANGAY', 0, 1, 'C');
            $pdf->SetFont($indigencyFont, 'B', 12);
            $pdf->Cell(0, 7, 'BARANGAY CERTIFICATION', 0, 1, 'C');
            $pdf->Ln(6);
        }
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
    $writeRichParagraph = function (
        array $segments,
        float $lineHeight,
        float $leftMargin,
        float $indent,
        string $fontFamily,
        float $fontSize,
        float $restoreLeftMargin = 18.0
    ) use ($pdf): void {
        $tokens = [];
        foreach ($segments as $segment) {
            $text = (string)($segment['text'] ?? '');
            if ($text === '') {
                continue;
            }
            $bold = !empty($segment['bold']);
            $parts = preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
            if (!is_array($parts)) {
                $parts = [$text];
            }
            foreach ($parts as $part) {
                $tokens[] = ['text' => $part, 'bold' => $bold];
            }
        }
        if (empty($tokens)) {
            return;
        }

        $contentWidth = $pdf->GetPageWidth() - $leftMargin - $restoreLeftMargin;
        $firstLineWidth = max(1.0, $contentWidth - $indent);

        $measureToken = function (array $token) use ($pdf, $fontFamily, $fontSize): float {
            $pdf->SetFont($fontFamily, !empty($token['bold']) ? 'B' : '', $fontSize);
            return $pdf->GetStringWidth((string)($token['text'] ?? ''));
        };

        $lines = [];
        $line = [];
        $lineWidth = 0.0;
        $lineMax = $firstLineWidth;

        foreach ($tokens as $token) {
            $text = (string)($token['text'] ?? '');
            if ($text === '') {
                continue;
            }
            $width = $measureToken($token);

            if (!empty($line) && ($lineWidth + $width) > $lineMax) {
                while (!empty($line) && preg_match('/^\s+$/u', (string)$line[count($line) - 1]['text'])) {
                    $removed = array_pop($line);
                    if ($removed !== null) {
                        $lineWidth -= $measureToken($removed);
                    }
                }
                if (!empty($line)) {
                    $lines[] = $line;
                }
                $line = [];
                $lineWidth = 0.0;
                $lineMax = $contentWidth;
                if (preg_match('/^\s+$/u', $text)) {
                    continue;
                }
            }

            $line[] = $token;
            $lineWidth += $width;
        }
        if (!empty($line)) {
            $lines[] = $line;
        }

        $y = $pdf->GetY();
        foreach ($lines as $lineIndex => $lineTokens) {
            $x = $leftMargin + ($lineIndex === 0 ? $indent : 0.0);
            $pdf->SetXY($x, $y);
            foreach ($lineTokens as $token) {
                $text = (string)($token['text'] ?? '');
                $pdf->SetFont($fontFamily, !empty($token['bold']) ? 'B' : '', $fontSize);
                $w = $pdf->GetStringWidth($text);
                $pdf->Cell($w, $lineHeight, $text, 0, 0, 'L');
            }
            $y += $lineHeight;
        }

        $pdf->SetLeftMargin($restoreLeftMargin);
        $pdf->SetX($restoreLeftMargin);
        $pdf->SetY($y);
    };
    $writeIndentedParagraph = function (
        string $text,
        float $lineHeight,
        float $leftMargin,
        float $indent,
        string $fontFamily,
        float $fontSize,
        float $restoreLeftMargin = 18.0
    ) use ($writeRichParagraph): void {
        $writeRichParagraph(
            [['text' => $text, 'bold' => false]],
            $lineHeight,
            $leftMargin,
            $indent,
            $fontFamily,
            $fontSize,
            $restoreLeftMargin
        );
    };
    if ($isSpecialCertificate) {
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

        if ($isIndigency) {
            $pdf->SetFont($indigencyFont, 'B', 12);
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
                $pdf->SetFont($indigencyFont, 'B', 11);
                $pdf->Cell(0, 7, strtoupper($firstLine), 0, 1, 'L');
                $pdf->SetX(39);
                for ($i = 1; $i < count($offLines); $i++) {
                    $line = trim((string)$offLines[$i]);
                    if ($line === '') continue;
                    $pdf->Cell(0, 7, strtoupper($line), 0, 1, 'L');
                    $pdf->SetX(39);
                }
            }
        } else {
            $pdf->SetFont($indigencyFont, 'B', 12);
            $pdf->SetX(18);
            $pdf->Cell(0, 7, 'TO WHOM IT MAY CONCERN::', 0, 1, 'L');
        }
        $pdf->Ln(7);
        $pdf->SetFont($indigencyFont, '', 12);
        if ($isIndigency) {
            $writeRichParagraph(
                [
                    ['text' => 'This is to certify that ', 'bold' => false],
                    ['text' => $fullName, 'bold' => true],
                    ['text' => ', resident of ' . $address . ' belongs to the one of the indigent families of this Barangay. The Income of this family is barely enough to meet their day-to-day needs.', 'bold' => false],
                ],
                7,
                18,
                10,
                $indigencyFont,
                12
            );
        } else {
            $writeRichParagraph(
                [
                    ['text' => 'This is to certify that ', 'bold' => false],
                    ['text' => $fullName, 'bold' => true],
                    ['text' => ', resident of ', 'bold' => false],
                    ['text' => $address, 'bold' => true],
                    ['text' => ' is personally known to be as a person of ', 'bold' => false],
                    ['text' => 'GOOD MORAL CHARACTER, PEACEFUL and LAW-ABIDING CITIZEN of THE COMMUNITY.', 'bold' => true],
                ],
                7,
                18,
                10,
                $indigencyFont,
                12
            );
        }
        $pdf->Ln(4);
        if ($isIndigency) {
            $writeRichParagraph(
                [
                    ['text' => 'This certification is being issued upon the request of the above subject in person in connection with his/her application for ', 'bold' => false],
                    ['text' => $requestPurpose, 'bold' => true],
                    ['text' => ' purposes only.', 'bold' => false],
                ],
                7,
                18,
                10,
                $indigencyFont,
                12
            );
        } else {
            $writeIndentedParagraph(
                'This further certifies that he/she is not a member, nor has joined a subversive society organization against the government.',
                7,
                18,
                10,
                $indigencyFont,
                12
            );
            $pdf->Ln(4);
            $writeRichParagraph(
                [
                    ['text' => 'This certification is being issued upon the request of the above-named person to be used for his/her application for ', 'bold' => false],
                    ['text' => $requestPurpose, 'bold' => true],
                    ['text' => ' purposes only.', 'bold' => false],
                ],
                7,
                18,
                10,
                $indigencyFont,
                12
            );
        }
        $pdf->Ln(4);
        $writeRichParagraph(
            [
                ['text' => 'Issued this ', 'bold' => false],
                ['text' => $issuedAsDocx, 'bold' => true],
                ['text' => ', at the office of the Punong Barangay, Barangay San Jose, Montalban, Rizal', 'bold' => false],
            ],
            7,
            18,
            10,
            $indigencyFont,
            12
        );

        if ($isGoodMoral) {
            $metaY = 186.0;
            $lineX1 = 36.0;
            $lineX2 = 58.0;
            $pdf->SetFont($indigencyFont, 'B', 12);
            $pdf->SetXY(18, $metaY);
            $pdf->Cell(18, 6, 'CTC No.:', 0, 0, 'L');
            $pdf->Line($lineX1, $metaY + 5, $lineX2, $metaY + 5);
            $metaY += 8;
            $pdf->SetFont($indigencyFont, 'B', 12);
            $pdf->SetXY(18, $metaY);
            $pdf->Cell(20, 6, 'Issued at:', 0, 0, 'L');
            $pdf->Line($lineX1, $metaY + 5, $lineX2, $metaY + 5);
            $metaY += 8;
            $pdf->SetXY(18, $metaY);
            $pdf->Cell(20, 6, 'Issued On:', 0, 0, 'L');
            $pdf->Line($lineX1, $metaY + 5, $lineX2, $metaY + 5);
            $metaY += 8;
            $pdf->SetXY(18, $metaY);
            $pdf->Cell(18, 6, 'OR No.:', 0, 0, 'L');
            $pdf->Line($lineX1, $metaY + 5, $lineX2, $metaY + 5);
            if ($orNo !== '') {
                $pdf->SetXY($lineX1, $metaY);
                $pdf->SetFont($indigencyFont, '', 11);
                $pdf->Cell($lineX2 - $lineX1, 6, $orNo, 0, 0, 'C');
            }

        }

        // Issued by + signatory blocks aligned to the same baseline.
        $signBaseY = $isGoodMoral ? 230.0 : 214.0;

        // Issued by block (lower-left)
        $pdf->SetFont($indigencyFont, '', 11);
        $pdf->SetXY(26, $signBaseY + 9);
        $pdf->Cell(20, 6, 'Issued by:', 0, 0, 'L');
        $pdf->SetFont($indigencyFont, 'B', 11);
        $pdf->Cell(54, 6, 'MINERVA D. QUITA', 0, 1, 'L');
        $pdf->SetFont($indigencyFont, 'I', 11);
        $pdf->SetXY(46, $signBaseY + 15);
        $pdf->Cell(44, 6, 'Barangay Secretary', 0, 1, 'C');

        // Punong Barangay signature block (lower-right)
        $pdf->Line(124, $signBaseY, 194, $signBaseY);
        $pdf->SetFont($indigencyFont, 'B', 11);
        $pdf->SetXY(124, $signBaseY + 2);
        $pdf->Cell(70, 6, 'HON. GLENN S. EVANGELISTA', 0, 1, 'C');
        $pdf->SetFont($indigencyFont, 'I', 11);
        $pdf->SetXY(124, $signBaseY + 8);
        $pdf->Cell(70, 6, 'Punong Barangay', 0, 1, 'C');

        // Footer note in a centered constrained width area.
        $pdf->SetFont($indigencyFont, 'I', 8);
        $pdf->SetXY(46, 258);
        $pdf->SetFont($indigencyFont, 'I', 8);
        $pdf->MultiCell(118, 4, "This certificate is valid for Forty-five (45) days from the date of issue, check the\nQR code to verify the authenticity of this document.", 0, 'C');

        $pdf->Output('F', $diskPath);
        return '/UnifiedFileAttachment/IssuedDocuments/Generated/' . $fileName;
    } else {
        if (is_file($qrDiskPath)) {
            $pdf->Image($qrDiskPath, 166, 238, 26, 26);
        }
        $writeIndentedParagraph(
            'This is to certify that ' . $fullName . ' is a bona fide resident of ' . $address . '.',
            7,
            18,
            10,
            $fontFace,
            12
        );
        $pdf->Ln(2);
        $writeIndentedParagraph(
            'This certification is issued upon request for ' . ($purpose !== '' ? $purpose : 'legal purpose') . '.',
            7,
            18,
            10,
            $fontFace,
            12
        );
        $pdf->Ln(2);
        $writeIndentedParagraph(
            'Issued this ' . $issuedAt . ' at Barangay San Jose, Rodriguez, Rizal.',
            7,
            18,
            10,
            $fontFace,
            12
        );

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

function dra_convert_docx_to_pdf(string $docxDiskPath, string $outDir): ?string {
    $docxReal = realpath($docxDiskPath);
    $outReal = realpath($outDir);
    if ($docxReal === false || $outReal === false || !is_file($docxReal)) {
        return null;
    }

    $baseName = pathinfo($docxReal, PATHINFO_FILENAME);
    $expectedPdf = $outReal . '/' . $baseName . '.pdf';
    @unlink($expectedPdf);

    $envSoffice = trim((string)getenv('SOFFICE_BIN'));
    $candidates = [
        $envSoffice,
        '/Applications/LibreOffice.app/Contents/MacOS/soffice',
        '/usr/bin/soffice',
        '/usr/local/bin/soffice',
        'libreoffice',
        'soffice',
    ];

    $attempted = [];

    foreach ($candidates as $bin) {
        if (trim($bin) === '') {
            continue;
        }
        $attempted[] = $bin;
        $cmd = escapeshellcmd($bin)
            . ' --headless --convert-to pdf:writer_pdf_Export '
            . '--outdir ' . escapeshellarg($outReal) . ' '
            . escapeshellarg($docxReal);

        $stdout = '';
        $stderr = '';
        $exitCode = 1;

        if (function_exists('proc_open')) {
            $spec = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $proc = @proc_open($cmd, $spec, $pipes);
            if (is_resource($proc)) {
                if (isset($pipes[0]) && is_resource($pipes[0])) {
                    fclose($pipes[0]);
                }
                $stdout = isset($pipes[1]) && is_resource($pipes[1]) ? (string)stream_get_contents($pipes[1]) : '';
                $stderr = isset($pipes[2]) && is_resource($pipes[2]) ? (string)stream_get_contents($pipes[2]) : '';
                if (isset($pipes[1]) && is_resource($pipes[1])) {
                    fclose($pipes[1]);
                }
                if (isset($pipes[2]) && is_resource($pipes[2])) {
                    fclose($pipes[2]);
                }
                $exitCode = (int)proc_close($proc);
            }
        }

        if ($exitCode !== 0 && function_exists('exec')) {
            $outLines = [];
            @exec($cmd . ' 2>&1', $outLines, $execExitCode);
            $stdout = trim(implode("\n", $outLines));
            $exitCode = (int)$execExitCode;
        }

        if ($exitCode !== 0 && function_exists('shell_exec')) {
            $shellOut = @shell_exec($cmd . ' 2>&1');
            $stdout = trim((string)$shellOut);
            $exitCode = is_file($expectedPdf) && filesize($expectedPdf) > 0 ? 0 : 1;
        }

        if ($exitCode === 0 && is_file($expectedPdf) && filesize($expectedPdf) > 0) {
            return $expectedPdf;
        }
        if (trim((string)$stdout) !== '' || trim((string)$stderr) !== '') {
            error_log('[dra_convert_docx_to_pdf] ' . trim((string)$stdout . ' ' . (string)$stderr));
        }
    }

    error_log('[dra_convert_docx_to_pdf] no working DOCX->PDF converter found. tried=' . implode(', ', $attempted));
    return null;
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
            if ($issuedPath === '') {
                continue;
            }
            $patch = [
                'ready_at' => dr_now(),
                'issued_file_path' => $issuedPath,
            ];
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

if ($action !== 'list') {
    dra_backfill_payment_verified_to_ready($conn);
}

if ($action === 'list') {
    $where = [];
    $types = '';
    $vals = [];
    $listContext = strtolower(trim((string)($_GET['list_context'] ?? '')));
    $isFinanceList = ($listContext === 'finance');
    $liteList = ((string)($_GET['lite'] ?? '0') === '1');
    $limit = max(1, min(500, (int)($_GET['limit'] ?? 250)));

    $stageCol = dr_column_exists($conn, 'documentrequesttbl', 'stage') ? 'stage' : null;
    $stage = trim((string)($_GET['stage'] ?? ''));
    if ($stage !== '') {
        if ($stageCol !== null) {
            $where[] = 'd.' . $stageCol . ' = ?';
            $types .= 's';
            $vals[] = $stage;
        } else {
            $statusId = dr_find_request_status_id_by_stage($conn, $stage);
            $statusCol = dr_request_status_column($conn);
            if ($statusId !== null && $statusCol !== null) {
                $where[] = 'd.' . $statusCol . ' = ?';
                $types .= 'i';
                $vals[] = $statusId;
            }
        }
    }
    if ($isFinanceList) {
        // Do not hard-filter finance list by stage in SQL.
        // Some legacy rows rely on status_id_request/transaction status and may have stale/empty stage.
        // Filtering is handled client-side by status bucket to keep rows visible for finance action.
    }
    $search = trim((string)($_GET['q'] ?? ''));
    if ($search !== '') {
        $parts = ['d.request_id LIKE ?'];
        $types .= 's';
        $vals[] = '%' . $search . '%';
        if (dr_column_exists($conn, 'documentrequesttbl', 'resident_id')) {
            $parts[] = 'd.resident_id LIKE ?';
            $types .= 's';
            $vals[] = '%' . $search . '%';
        }
        $parts[] = 'd.request_details LIKE ?';
        $types .= 's';
        $vals[] = '%' . $search . '%';
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }

    $extraSelects = [];
    $extraJoins = [];
    $hasIssuanceTable = (!$liteList) && dr_table_exists($conn, 'issuancerequesttbl');
    $hasResidentInfoTable = (!$liteList) && dr_table_exists($conn, 'residentinformationtbl');
    if ($hasIssuanceTable) {
        $extraSelects[] = "i.certificate_type AS _issuance_certificate_type";
        $extraSelects[] = "i.certificate_number AS _issuance_certificate_number";
        $extraSelects[] = "i.verification_code AS _issuance_verification_code";
        $extraJoins[] = "LEFT JOIN issuancerequesttbl i ON i.request_id = d.request_id";
    }
    if ($hasResidentInfoTable && !$isFinanceList) {
        $extraSelects[] = "TRIM(CONCAT_WS(' ', NULLIF(riu.firstname, ''), NULLIF(riu.middlename, ''), NULLIF(riu.lastname, ''), NULLIF(riu.suffix, ''))) AS _resident_name_by_user";
        $extraSelects[] = "TRIM(CONCAT_WS(' ', NULLIF(rir.firstname, ''), NULLIF(rir.middlename, ''), NULLIF(rir.lastname, ''), NULLIF(rir.suffix, ''))) AS _resident_name_by_resident";
        $extraJoins[] = "LEFT JOIN residentinformationtbl riu ON riu.user_id = d.resident_user_id";
        $extraJoins[] = "LEFT JOIN residentinformationtbl rir ON rir.resident_id = d.resident_id";
    }

    $baseSelects = [
        "d.request_id AS request_id",
        dra_select_or_null($conn, 'documentrequesttbl', 'resident_user_id', 'resident_user_id'),
        dra_select_or_null($conn, 'documentrequesttbl', 'resident_id', 'resident_id'),
        dra_select_or_null($conn, 'documentrequesttbl', 'resident_name', 'resident_name'),
        dra_select_or_null($conn, 'documentrequesttbl', 'document_type', 'document_type'),
        dra_select_or_null($conn, 'documentrequesttbl', 'purpose', 'purpose'),
        dra_select_or_null($conn, 'documentrequesttbl', 'status_remarks', 'status_remarks'),
        dra_select_or_null($conn, 'documentrequesttbl', 'status_reason', 'status_reason'),
        dra_select_or_null($conn, 'documentrequesttbl', 'stage', 'stage'),
        dra_select_or_null($conn, 'documentrequesttbl', 'submitted_at', 'submitted_at'),
        dra_select_or_null($conn, 'documentrequesttbl', 'request_timestamp', 'request_timestamp'),
        dra_select_or_null($conn, 'documentrequesttbl', 'certificate_number', 'certificate_number'),
        dra_select_or_null($conn, 'documentrequesttbl', 'verification_code', 'verification_code'),
        dra_select_or_null($conn, 'documentrequesttbl', 'user_id_official_reviewed_by', 'user_id_official_reviewed_by'),
        dra_select_or_null($conn, 'documentrequesttbl', 'user_id_official_released_by', 'user_id_official_released_by'),
        dra_select_or_null($conn, 'documentrequesttbl', 'review_timestamp', 'review_timestamp'),
        dra_select_or_null($conn, 'documentrequesttbl', 'release_timestamp', 'release_timestamp'),
    ];
    if (dr_column_exists($conn, 'documentrequesttbl', 'status_id_request')) {
        $baseSelects[] = "d.status_id_request AS status_id_request";
    }
    if (dr_column_exists($conn, 'documentrequesttbl', 'status_id')) {
        $baseSelects[] = "d.status_id AS status_id";
    }
    if (!$isFinanceList && !$liteList) {
        if (dr_column_exists($conn, 'documentrequesttbl', 'request_details')) {
            $baseSelects[] = "d.request_details AS request_details";
        } else {
            $baseSelects[] = "NULL AS request_details";
        }
    } else {
        $baseSelects[] = "NULL AS request_details";
    }

    $sql = "
        SELECT
            " . implode(",\n            ", $baseSelects) . ",
            f.transaction_amount AS _tx_amount,
            f.payment_method AS _tx_payment_method,
            f.payment_proof_path AS _tx_payment_proof_path,
            f.transaction_details AS _tx_transaction_details,
            f.or_number AS _tx_or_number,
            f.transaction_status_id AS _tx_status_id,
            s.status_name AS _tx_status_name,
            f.payment_deadline AS _tx_payment_deadline,
            f.payment_timestamp AS _tx_payment_timestamp,
            f.finance_decision_at AS _tx_finance_decision_at,
            f.user_id_employee_process AS _tx_finance_user_id
            " . ($extraSelects ? ",\n            " . implode(",\n            ", $extraSelects) : "") . "
        FROM documentrequesttbl d
        LEFT JOIN financetransactiontbl f ON f.request_id = d.request_id
        LEFT JOIN statuslookuptbl s ON s.status_id = f.transaction_status_id
        " . ($extraJoins ? "\n        " . implode("\n        ", $extraJoins) : "") . "
    ";
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $orderCol = dr_column_exists($conn, 'documentrequesttbl', 'submitted_at') ? 'submitted_at' : 'request_timestamp';
    $sql .= ' ORDER BY d.' . $orderCol . ' DESC, d.request_id DESC LIMIT ' . $limit;

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
    $feeByDocType = [];
    $rs = $stmt->get_result();
    while ($row = $rs->fetch_assoc()) {
        $docType = trim((string)($row['document_type'] ?? ''));
        if ($docType === '') {
            $docType = trim((string)($row['_issuance_certificate_type'] ?? ''));
            if ($docType !== '') {
                $row['document_type'] = $docType;
            }
        }
        if (trim((string)($row['certificate_number'] ?? '')) === '') {
            $issuedCertNo = trim((string)($row['_issuance_certificate_number'] ?? ''));
            if ($issuedCertNo !== '') {
                $row['certificate_number'] = $issuedCertNo;
            }
        }
        if (trim((string)($row['verification_code'] ?? '')) === '') {
            $issuedVc = trim((string)($row['_issuance_verification_code'] ?? ''));
            if ($issuedVc !== '') {
                $row['verification_code'] = $issuedVc;
            }
        }
        if (trim((string)($row['submitted_at'] ?? '')) === '' && trim((string)($row['request_timestamp'] ?? '')) !== '') {
            $row['submitted_at'] = (string)$row['request_timestamp'];
        }

        // Populate finance data from joined columns (avoids per-row finance query).
        $row['amount'] = isset($row['_tx_amount']) ? (float)$row['_tx_amount'] : null;
        $row['payment_method'] = (string)($row['_tx_payment_method'] ?? '');
        $row['payment_proof_path'] = (string)($row['_tx_payment_proof_path'] ?? '');
        $row['or_number'] = (string)($row['_tx_or_number'] ?? '');
        $row['payment_status_id'] = isset($row['_tx_status_id']) ? (int)$row['_tx_status_id'] : 0;
        $row['payment_status_name'] = (string)($row['_tx_status_name'] ?? '');
        $row['payment_deadline'] = (string)($row['_tx_payment_deadline'] ?? '');
        $row['payment_submitted_at'] = (string)($row['_tx_payment_timestamp'] ?? '');
        $row['finance_decision_at'] = (string)($row['_tx_finance_decision_at'] ?? '');
        $row['finance_user_id'] = (string)($row['_tx_finance_user_id'] ?? '');
        $txDetails = (string)($row['_tx_transaction_details'] ?? '');
        if (!$liteList && $txDetails !== '') {
            $decoded = json_decode($txDetails, true);
            if (is_array($decoded)) {
                $ref = trim((string)($decoded['reference'] ?? ''));
                if ($ref !== '') {
                    $row['payment_reference'] = $ref;
                }
                if (trim((string)($row['purpose'] ?? '')) === '') {
                    $purposeFromTx = trim((string)($decoded['purpose'] ?? ''));
                    if ($purposeFromTx !== '') {
                        $row['purpose'] = $purposeFromTx;
                    }
                }
            } elseif (preg_match('/\bReference:\s*(.+)$/mi', $txDetails, $m)) {
                $row['payment_reference'] = trim((string)($m[1] ?? ''));
            }
        }

        if (!$isFinanceList && trim((string)($row['resident_name'] ?? '')) === '') {
            $resolvedResidentName = trim((string)($row['_resident_name_by_user'] ?? ''));
            if ($resolvedResidentName === '') {
                $resolvedResidentName = trim((string)($row['_resident_name_by_resident'] ?? ''));
            }
            if ($resolvedResidentName !== '') {
                $row['resident_name'] = $resolvedResidentName;
                $row['full_name'] = $resolvedResidentName;
            }
        }

        if (trim((string)($row['stage'] ?? '')) === '') {
            dr_sync_stage_from_status_lookup($conn, $row);
        }
        $row['stage_label'] = dr_stage_label((string)$row['stage']);
        $row['fee_amount'] = null;
        $row['_doc_type_for_fee'] = trim((string)($row['document_type'] ?? ''));
        if ($isFinanceList || $liteList) {
            // Keep finance list response lean; detailed request payload is not needed on initial list render.
            $row['payload'] = [];
        } else {
            $payload = json_decode((string)($row['request_details'] ?? $row['payload_json'] ?? '{}'), true);
            $row['payload'] = is_array($payload) ? $payload : [];
            if (trim((string)($row['document_type'] ?? '')) === '') {
                $payloadDocType = trim((string)($row['payload']['document_type'] ?? ''));
                if ($payloadDocType !== '') {
                    $row['document_type'] = $payloadDocType;
                }
            }
            if (trim((string)($row['purpose'] ?? '')) === '') {
                $payloadPurpose = trim((string)($row['payload']['request_purpose'] ?? $row['payload']['purpose'] ?? ''));
                if ($payloadPurpose !== '') {
                    $row['purpose'] = $payloadPurpose;
                }
            }
            if (trim((string)($row['resident_name'] ?? '')) === '') {
                $payloadResidentName = trim((string)($row['payload']['resident_name'] ?? ''));
                if ($payloadResidentName !== '') {
                    $row['resident_name'] = $payloadResidentName;
                }
            }
        }
        // Keep list payload light to avoid per-row profile queries (major latency source).
        // Full resident profile is loaded on-demand from resident masterlist endpoint when needed.
        $row['resident_profile'] = [];
        unset(
            $row['_tx_amount'],
            $row['_tx_payment_method'],
            $row['_tx_payment_proof_path'],
            $row['_tx_transaction_details'],
            $row['_tx_or_number'],
            $row['_tx_status_id'],
            $row['_tx_status_name'],
            $row['_tx_payment_deadline'],
            $row['_tx_payment_timestamp'],
            $row['_tx_finance_decision_at'],
            $row['_tx_finance_user_id'],
            $row['_issuance_certificate_type'],
            $row['_issuance_certificate_number'],
            $row['_issuance_verification_code'],
            $row['_resident_name_by_user'],
            $row['_resident_name_by_resident']
        );
        $items[] = $row;
    }
    $stmt->close();

    if ($items && !$liteList) {
        $docTypesForFee = [];
        foreach ($items as $row) {
            $docTypeForFee = trim((string)($row['_doc_type_for_fee'] ?? ''));
            if ($docTypeForFee !== '') {
                $docTypesForFee[$docTypeForFee] = true;
            }
        }
        $feeByDocType = dra_get_fee_map_for_document_types($conn, array_keys($docTypesForFee));
        foreach ($items as &$row) {
            $docTypeForFee = trim((string)($row['_doc_type_for_fee'] ?? ''));
            if ($docTypeForFee !== '') {
                if (!array_key_exists($docTypeForFee, $feeByDocType)) {
                    $feeByDocType[$docTypeForFee] = dr_get_fee_amount_for_document_type($conn, $docTypeForFee);
                }
                $row['fee_amount'] = $feeByDocType[$docTypeForFee];
            } else {
                $row['fee_amount'] = null;
            }
            unset($row['_doc_type_for_fee']);
        }
        unset($row);
    }

    dr_respond_json(200, ['success' => true, 'items' => $items]);
}

$requestId = trim((string)($_POST['request_id'] ?? $_GET['request_id'] ?? ''));
if ($action === 'get_request') {
    if ($requestId === '') {
        dr_respond_json(422, ['success' => false, 'message' => 'Missing request ID.']);
    }
    $row = dr_fetch_request($conn, $requestId);
    if (!$row) {
        dr_respond_json(404, ['success' => false, 'message' => 'Request not found.']);
    }

    $payload = json_decode((string)($row['request_details'] ?? $row['payload_json'] ?? '{}'), true);
    $row['payload'] = is_array($payload) ? $payload : [];

    $residentUserId = trim((string)($row['resident_user_id'] ?? ''));
    $residentId = trim((string)($row['resident_id'] ?? ''));
    $row['resident_profile'] = dra_resident_profile_snapshot($conn, $residentUserId, $residentId);
    $row['stage_label'] = dr_stage_label((string)($row['stage'] ?? ''));
    $row['fee_amount'] = dr_get_fee_amount_for_document_type($conn, (string)($row['document_type'] ?? ''));

    dr_respond_json(200, ['success' => true, 'item' => $row]);
}
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
    if (dra_is_finance_user($conn, $currentUserId)) {
        http_response_code(403);
        exit('Finance users are not allowed to view issued documents.');
    }
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
    if (!in_array($stage, [DR_STAGE_PAYMENT_VERIFIED, DR_STAGE_READY_FOR_CLAIM, DR_STAGE_COMPLETED], true)) {
        http_response_code(422);
        exit('Issued document is not available for this request stage yet.');
    }
    $publicPath = trim((string)($row['issued_file_path'] ?? ''));
    $docTypeNorm = strtolower(trim((string)($row['document_type'] ?? '')));
    $isIndigency = strpos($docTypeNorm, 'indigency') !== false;
    $isGoodMoral = (strpos($docTypeNorm, 'goodmoral') !== false) || (strpos($docTypeNorm, 'good moral') !== false);
    $isResidency = strpos($docTypeNorm, 'residency') !== false;
    $ext = strtolower(pathinfo($publicPath, PATHINFO_EXTENSION));
    $mustRegenerate = ($publicPath === '')
        || $isResidency
        || (($isIndigency || $isGoodMoral) && $ext !== 'pdf');
    if ($mustRegenerate) {
        $verificationCode = trim((string)($row['verification_code'] ?? ''));
        if ($verificationCode === '') {
            $verificationCode = strtoupper(bin2hex(random_bytes(8)));
        }
        $generated = (string)(dra_generate_issued_document_safe(array_merge((array)$row, [
            'verification_code' => $verificationCode,
        ])) ?? '');
        if ($generated !== '') {
            $patch = [
                'issued_file_path' => $generated,
            ];
            if (trim((string)($row['verification_code'] ?? '')) === '') {
                $patch['verification_code'] = $verificationCode;
                $patch['qr_code_path'] = '/UnifiedFileAttachment/IssuedDocuments/QR/qr_' . preg_replace('/[^A-Za-z0-9_-]/', '', $requestId) . '.png';
            }
            dr_update_stage($conn, $requestId, (string)($row['stage'] ?? ''), $patch);
            $publicPath = $generated;
        }
    }
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
        // Keep approval fast: defer heavy PDF/QR generation until view/release time.
        $patch['verification_code'] = $verificationCode;
        $patch['qr_code_path'] = $qrCodePath;
        $patch['ready_at'] = dr_now();
    }

    $updated = dr_update_stage($conn, $requestId, $nextStage, $patch);
    if (!$updated) {
        dr_respond_json(500, ['success' => false, 'message' => 'Unable to approve request.']);
    }

    dra_send_notification_deferred(
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

    dra_send_notification_deferred(
        $conn,
        $updated,
        'Document Request Rejected',
        dra_request_notice($updated, $requestId, 'rejected. Reason: ' . $reason)
    );

    dr_respond_json(200, ['success' => true, 'request' => $updated]);
}

if ($action === 'finance_verify') {
    $currentStage = strtolower(trim((string)($row['stage'] ?? '')));
    if (!in_array($currentStage, [DR_STAGE_FOR_PAYMENT, DR_STAGE_PAYMENT_SUBMITTED, DR_STAGE_PAYMENT_REJECTED], true)) {
        dr_respond_json(422, ['success' => false, 'message' => 'Request is not eligible for finance verification.']);
    }
    $verifyMode = strtolower(trim((string)($_POST['verify_mode'] ?? '')));
    if (!in_array($verifyMode, ['walkin', 'gcash'], true)) {
        $verifyMode = '';
    }

    $amountRaw = trim((string)($_POST['amount'] ?? ''));
    $orNumber = trim((string)($_POST['or_number'] ?? ''));
    $defaultFee = dr_get_fee_amount_for_document_type($conn, (string)($row['document_type'] ?? ''));
    $resolvedAmount = null;
    if ($defaultFee !== null) {
        // Finance amount is system-controlled from configured fee.
        $resolvedAmount = (float)$defaultFee;
    } elseif (isset($row['amount']) && $row['amount'] !== null && is_numeric((string)$row['amount'])) {
        $resolvedAmount = (float)$row['amount'];
    } elseif ($amountRaw !== '' && is_numeric($amountRaw)) {
        // Fallback only when fee is not configured.
        $resolvedAmount = (float)$amountRaw;
    }

    if ($resolvedAmount === null || $resolvedAmount < 0) {
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
    if ($issuedPath === null || $issuedPath === '') {
        dr_respond_json(500, ['success' => false, 'message' => 'Payment verified, but issued document generation failed.']);
    }

    $patch = [
        'amount' => $resolvedAmount,
        'or_number' => $orNumber,
        'certificate_number' => $certificateNumber,
        'verification_code' => $verificationCode,
        'qr_code_path' => $qrCodePath,
        'status_reason' => null,
        'finance_user_id' => $currentUserId,
        'finance_decision_at' => dr_now(),
        'ready_at' => dr_now(),
    ];
    // Walk-in verification from for-payment/rejected states is treated as barangay payment.
    if ($verifyMode === 'walkin' || in_array($currentStage, [DR_STAGE_FOR_PAYMENT, DR_STAGE_PAYMENT_REJECTED], true)) {
        $patch['payment_method'] = 'barangay';
        $patch['payment_submitted_at'] = dr_now();
        $patch['payment_proof_path'] = null;
        $patch['payment_reference'] = null;
    } elseif ($verifyMode === 'gcash') {
        $patch['payment_method'] = 'gcash';
    }
    $patch['issued_file_path'] = (string)$issuedPath;

    // Payment verification immediately makes the document ready for claim/download.
    $updated = dr_update_stage($conn, $requestId, DR_STAGE_READY_FOR_CLAIM, $patch);

    if (!$updated) {
        dr_respond_json(500, ['success' => false, 'message' => 'Unable to verify payment.']);
    }

    dra_send_notification_deferred(
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

    dra_send_notification_deferred(
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
    if ($issuedPath === null || $issuedPath === '') {
        dr_respond_json(500, ['success' => false, 'message' => 'Unable to mark ready without an issued document.']);
    }

    $patch = [
        'ready_at' => dr_now(),
        'verification_code' => $verificationCode,
        'qr_code_path' => '/UnifiedFileAttachment/IssuedDocuments/QR/qr_' . preg_replace('/[^A-Za-z0-9_-]/', '', $requestId) . '.png',
    ];
    $patch['issued_file_path'] = (string)$issuedPath;

    $updated = dr_update_stage($conn, $requestId, DR_STAGE_READY_FOR_CLAIM, $patch);
    if (!$updated) {
        dr_respond_json(500, ['success' => false, 'message' => 'Unable to mark request ready.']);
    }

    dra_send_notification_deferred(
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

    dra_send_notification_deferred(
        $conn,
        $updated,
        'Document Request Completed',
        dra_request_notice($updated, $requestId, 'completed and released.')
    );

    dr_respond_json(200, ['success' => true, 'request' => $updated]);
}

dr_respond_json(404, ['success' => false, 'message' => 'Unknown action.']);
