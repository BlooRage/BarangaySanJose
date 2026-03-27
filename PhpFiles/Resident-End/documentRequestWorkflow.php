<?php
declare(strict_types=1);

require_once __DIR__ . '/../General/security.php';
require_once __DIR__ . '/../General/connection.php';
require_once __DIR__ . '/../General/documentRequestWorkflow.php';
require_once __DIR__ . '/../General/uploadLimits.php';

requireRoleSession(['Resident'], true);

$action = strtolower(trim((string)($_REQUEST['action'] ?? '')));
if ($action === '') {
    dr_respond_json(400, ['success' => false, 'message' => 'Missing action.']);
}

$drSubmitTiming = null;
if ($action === 'submit_request') {
    $drSubmitTiming = [
        'started_at' => microtime(true),
        'request_id' => '',
        'resident_user_id' => (string)($_SESSION['user_id'] ?? ''),
        'bootstrap_ran' => false,
    ];
    register_shutdown_function(static function () use (&$drSubmitTiming): void {
        if (!is_array($drSubmitTiming) || !isset($drSubmitTiming['started_at'])) {
            return;
        }
        $elapsedMs = (microtime(true) - (float)$drSubmitTiming['started_at']) * 1000;
        $status = http_response_code();
        if (!is_int($status) || $status <= 0) {
            $status = 200;
        }
        error_log(sprintf(
            '[documentRequestWorkflow][submit_request][timing] status=%d elapsed_ms=%.2f request_id=%s resident_user_id=%s bootstrap=%s',
            $status,
            $elapsedMs,
            trim((string)($drSubmitTiming['request_id'] ?? '')) !== '' ? (string)$drSubmitTiming['request_id'] : '-',
            trim((string)($drSubmitTiming['resident_user_id'] ?? '')) !== '' ? (string)$drSubmitTiming['resident_user_id'] : '-',
            !empty($drSubmitTiming['bootstrap_ran']) ? '1' : '0'
        ));
    });
}

function dr_ensure_request_support_tables(mysqli $conn): void {
    $conn->query("
        CREATE TABLE IF NOT EXISTS documenttypelookuptbl (
            document_type_id INT(11) NOT NULL AUTO_INCREMENT,
            document_type_name VARCHAR(100) NOT NULL,
            document_category VARCHAR(100) NOT NULL,
            PRIMARY KEY (document_type_id),
            UNIQUE KEY uq_document_type_name (document_type_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS unifiedfileattachmenttbl (
            attachment_id BIGINT(20) UNSIGNED NOT NULL,
            source_type VARCHAR(50) NOT NULL,
            source_id VARCHAR(12) NOT NULL,
            document_type_id INT(11) NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_type VARCHAR(50) NOT NULL,
            upload_timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            user_id_uploaded_by VARCHAR(12) NOT NULL,
            status_id_verify INT(11) NOT NULL,
            remarks TEXT DEFAULT NULL,
            id_number VARCHAR(100) DEFAULT NULL,
            deleted_at DATETIME DEFAULT NULL,
            delete_reason VARCHAR(100) DEFAULT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (attachment_id),
            KEY idx_source (source_type, source_id),
            KEY idx_doc_type (document_type_id),
            KEY idx_uploaded_by (user_id_uploaded_by),
            KEY idx_verify_status (status_id_verify),
            CONSTRAINT fk_ufa_document_type FOREIGN KEY (document_type_id) REFERENCES documenttypelookuptbl (document_type_id),
            CONSTRAINT fk_ufa_uploaded_by FOREIGN KEY (user_id_uploaded_by) REFERENCES useraccountstbl (user_id),
            CONSTRAINT fk_ufa_verify_status FOREIGN KEY (status_id_verify) REFERENCES statuslookuptbl (status_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    idg_ensure_numeric_generated_key($conn, 'unifiedfileattachmenttbl', 'attachment_id', 'BIGINT(20) UNSIGNED NOT NULL');
}

function dr_submit_bootstrap_needed(mysqli $conn): bool {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    // Fast guard: only run heavy migration bootstrap when core schema pieces are missing.
    $requiredTables = [
        'documentrequesttbl',
        'documenttypelookuptbl',
        'unifiedfileattachmenttbl',
        'generalfeestbl',
    ];
    foreach ($requiredTables as $table) {
        if (!dr_table_exists($conn, $table)) {
            $cached = true;
            return true;
        }
    }

    $requiredColumns = [
        'resident_user_id',
        'resident_id',
        'request_details',
        'status_id_request',
        'request_timestamp',
    ];
    foreach ($requiredColumns as $column) {
        if (!dr_column_exists($conn, 'documentrequesttbl', $column)) {
            $cached = true;
            return true;
        }
    }

    $cached = false;
    return false;
}

$bootstrapActions = ['submit_request'];
if (in_array($action, $bootstrapActions, true)) {
    if (dr_submit_bootstrap_needed($conn)) {
        if (is_array($drSubmitTiming)) {
            $drSubmitTiming['bootstrap_ran'] = true;
        }
        // Run migration/bootstrap only for incomplete installs, not every request submission.
        dr_ensure_request_support_tables($conn);
        dr_ensure_table($conn);
        dr_ensure_general_fees_table($conn);
    }
}

$userId = (string)($_SESSION['user_id'] ?? '');
$residentId = dr_get_resident_id($conn, $userId) ?? '';
$residentForeignId = $userId;

if ($residentId === '') {
    dr_respond_json(422, ['success' => false, 'message' => 'Resident profile is incomplete.']);
}

function dr_app_base_path(): string {
    $scriptName = str_replace("\\", "/", (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $pos = strpos($scriptName, '/PhpFiles/');
    $base = $pos !== false ? substr($scriptName, 0, $pos) : dirname($scriptName);
    $base = rtrim((string)$base, '/');
    if ($base === '.' || $base === '/') {
        return '';
    }
    return $base;
}

function dr_strip_legacy_base(string $publicPath): string {
    $publicPath = trim($publicPath);
    $base = dr_app_base_path();
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

function dr_wants_html_redirect(): bool {
    return (isset($_POST['redirect']) && $_POST['redirect'] === '1')
        || strpos((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'text/html') !== false;
}

function dr_redirect_to_barangay_id_landing(string $notice = ''): void {
    $url = appUrl('/Resident-End/BarangayId/BarangayIdLandingPage.php');
    if ($notice !== '') {
        $separator = strpos($url, '?') === false ? '?' : '&';
        $url .= $separator . 'notice=' . rawurlencode($notice);
    }
    header('Location: ' . $url);
    exit;
}

function dr_allowed_extension(string $name): bool {
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'pdf'], true);
}

function dr_save_upload(array $file, string $folder, ?array $allowedExtensions = null): array {
    $errorCode = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode === UPLOAD_ERR_NO_FILE) {
        return ['path' => null, 'error' => 'Please attach the required file.'];
    }

    $validationError = app_upload_validate_file($file, 'resident', 'File');
    if ($validationError !== null) {
        return ['path' => null, 'error' => $validationError];
    }

    $orig = (string)($file['name'] ?? '');
    if ($orig === '') {
        return ['path' => null, 'error' => 'Please attach the required file.'];
    }
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if ($allowedExtensions !== null) {
        $allowed = array_values(array_unique(array_map(static fn($v) => strtolower(trim((string)$v)), $allowedExtensions)));
        if (!in_array($ext, $allowed, true)) {
            return ['path' => null, 'error' => 'Unsupported file type.'];
        }
    } elseif (!dr_allowed_extension($orig)) {
        return ['path' => null, 'error' => 'Unsupported file type. Allowed: JPG, JPEG, PNG, WEBP, PDF.'];
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    if (!is_uploaded_file($tmp)) {
        return ['path' => null, 'error' => 'Upload validation failed. Please reselect the file and submit again.'];
    }

    $baseDir = realpath(__DIR__ . '/../../');
    if ($baseDir === false) {
        return ['path' => null, 'error' => 'Server path resolution failed. Please try again later.'];
    }

    $targetDir = $baseDir . '/UnifiedFileAttachment/' . trim($folder, '/');
    if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        return ['path' => null, 'error' => 'Server storage is unavailable. Please try again later.'];
    }
    @chmod($targetDir, 0775);
    // Probe writeability by creating a tiny temp file; this is more reliable than is_writable() alone.
    $probe = $targetDir . '/.__w_' . bin2hex(random_bytes(4)) . '.tmp';
    $probeOk = @file_put_contents($probe, 'ok');
    if ($probeOk === false) {
        error_log('[documentRequestWorkflow][upload] target dir not writable: ' . $targetDir);
        return ['path' => null, 'error' => 'Upload folder is not writable on server. Please contact admin.'];
    }
    @unlink($probe);

    $name = date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $targetPath = $targetDir . '/' . $name;

    if (!@move_uploaded_file($tmp, $targetPath)) {
        // Fallbacks for environments where move_uploaded_file intermittently fails.
        $copied = @copy($tmp, $targetPath);
        if (!$copied) {
            $renamed = @rename($tmp, $targetPath);
            if (!$renamed) {
                $lastError = error_get_last();
                error_log('[documentRequestWorkflow][upload] save failed tmp=' . $tmp . ' target=' . $targetPath . ' err=' . ($lastError['message'] ?? 'unknown'));
                return ['path' => null, 'error' => 'Failed to save uploaded file. Please try again.'];
            }
        }
    }
    @chmod($targetPath, 0664);
    if (!is_file($targetPath) || filesize($targetPath) <= 0) {
        @unlink($targetPath);
        error_log('[documentRequestWorkflow][upload] saved file invalid/empty: ' . $targetPath);
        return ['path' => null, 'error' => 'Uploaded file appears empty. Please re-upload a valid proof file.'];
    }

    return ['path' => '/UnifiedFileAttachment/' . trim($folder, '/') . '/' . $name, 'error' => null];
}


function dr_ensure_pdf_tools(): bool {
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }

    $autoloadPaths = [
        __DIR__ . '/../../composer-email-handler/vendor/autoload.php',
        __DIR__ . '/../../vendor/autoload.php',
    ];
    foreach ($autoloadPaths as $autoloadPath) {
        if (is_file($autoloadPath)) {
            require_once $autoloadPath;
        }
    }

    $ready = class_exists('\\setasign\\Fpdi\\Fpdi');
    return $ready;
}

function dr_append_image_page_to_pdf(\setasign\Fpdi\Fpdi $pdf, string $imagePath, string $declaredExt = ''): void {
    $imageInfo = @getimagesize($imagePath);
    if ($imageInfo === false || !isset($imageInfo[0], $imageInfo[1])) {
        throw new Exception('Invalid image file.');
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

    $imageType = strtolower(ltrim(trim($declaredExt), '.'));
    if ($imageType === 'jpg') {
        $imageType = 'jpeg';
    }

    $pdf->Image($imagePath, $x, $y, $drawW, $drawH, $imageType);
}

function dr_append_pdf_pages(\setasign\Fpdi\Fpdi $pdf, string $pdfPath): void {
    $pageCount = $pdf->setSourceFile($pdfPath);
    for ($i = 1; $i <= $pageCount; $i++) {
        $tpl = $pdf->importPage($i);
        $size = $pdf->getTemplateSize($tpl);
        $orientation = $size['width'] > $size['height'] ? 'L' : 'P';
        $pdf->AddPage($orientation, [$size['width'], $size['height']]);
        $pdf->useTemplate($tpl);
    }
}

function dr_move_uploaded_file_to_path(string $tmp, string $targetPath): bool {
    if (@move_uploaded_file($tmp, $targetPath)) {
        return true;
    }
    if (@copy($tmp, $targetPath)) {
        return true;
    }
    return @rename($tmp, $targetPath);
}

if ($action === 'report_barangay_id_lost') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        dr_respond_json(405, ['success' => false, 'message' => 'Method not allowed.']);
    }

    $targetRequestId = trim((string)($_POST['request_id'] ?? ''));
    $state = dr_resident_barangay_id_state($conn, $residentForeignId, $residentId);

    if ($targetRequestId === '') {
        if (dr_wants_html_redirect()) {
            dr_redirect_to_barangay_id_landing('lost_error');
        }
        dr_respond_json(422, ['success' => false, 'message' => 'Missing request ID.']);
    }

    $latestCompleted = is_array($state['latest_completed_request'] ?? null)
        ? $state['latest_completed_request']
        : null;
    if (!$latestCompleted || trim((string)($latestCompleted['request_id'] ?? '')) !== $targetRequestId) {
        if (dr_wants_html_redirect()) {
            dr_redirect_to_barangay_id_landing('lost_error');
        }
        dr_respond_json(422, ['success' => false, 'message' => 'Only your latest completed Barangay ID can be marked as lost.']);
    }

    if (!($state['can_report_lost'] ?? false)) {
        $message = 'This Barangay ID cannot be marked as lost right now.';
        if (($state['block_reason'] ?? '') === 'pending_request') {
            $message = 'You already have an active Barangay ID request in progress.';
        } elseif (($state['latest_completed_lost'] ?? false)) {
            $message = 'This Barangay ID was already tagged as lost.';
        }
        if (dr_wants_html_redirect()) {
            dr_redirect_to_barangay_id_landing('lost_error');
        }
        dr_respond_json(422, ['success' => false, 'message' => $message]);
    }

    $payload = dr_decode_request_payload($latestCompleted);
    $payload['barangay_id_lost_reported'] = true;
    $payload['barangay_id_lost_reported_at'] = dr_now();
    $payload['barangay_id_lost_reported_by_user_id'] = $residentForeignId;

    if (!dr_column_exists($conn, 'documentrequesttbl', 'request_details')) {
        dr_respond_json(500, ['success' => false, 'message' => 'Request details column is unavailable.']);
    }

    $encodedPayload = dr_safe_json($payload);
    $updateSets = ['request_details = ?'];
    $types = 's';
    $params = [$encodedPayload];
    if (dr_column_exists($conn, 'documentrequesttbl', 'updated_at')) {
        $updateSets[] = 'updated_at = ?';
        $types .= 's';
        $params[] = dr_now();
    }
    $sql = "
        UPDATE documentrequesttbl
        SET " . implode(', ', $updateSets) . "
        WHERE request_id = ?
          AND resident_user_id = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        dr_respond_json(500, ['success' => false, 'message' => 'Failed to prepare lost-status update.']);
    }

    $types .= 'ss';
    $params[] = $targetRequestId;
    $params[] = $residentForeignId;
    $refs = [];
    foreach ($params as $index => $value) {
        $refs[$index] = &$params[$index];
    }
    array_unshift($refs, $types);
    call_user_func_array([$stmt, 'bind_param'], $refs);
    $ok = $stmt->execute();
    $affected = (int)$stmt->affected_rows;
    $stmt->close();

    if (!$ok || $affected < 1) {
        if (dr_wants_html_redirect()) {
            dr_redirect_to_barangay_id_landing('lost_error');
        }
        dr_respond_json(500, ['success' => false, 'message' => 'Failed to tag the Barangay ID as lost.']);
    }

    if (dr_wants_html_redirect()) {
        dr_redirect_to_barangay_id_landing('lost_reported');
    }

    dr_respond_json(200, [
        'success' => true,
        'message' => 'Barangay ID tagged as lost. You may now submit a replacement request.',
        'request_id' => $targetRequestId,
    ]);
}

function dr_normalize_image_for_pdf(string $imagePath, string $ext): array {
    $ext = strtolower(trim($ext));
    if (in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
        return ['path' => $imagePath, 'cleanup' => false, 'error' => null];
    }

    if (!extension_loaded('gd') || !function_exists('imagecreatefromstring') || !function_exists('imagepng')) {
        return ['path' => null, 'cleanup' => false, 'error' => 'Image conversion support is unavailable on the server.'];
    }

    $imageData = @file_get_contents($imagePath);
    if ($imageData === false || $imageData === '') {
        return ['path' => null, 'cleanup' => false, 'error' => 'Unable to read uploaded image.'];
    }

    $image = @imagecreatefromstring($imageData);
    if ($image === false) {
        if ($ext === 'webp') {
            return ['path' => null, 'cleanup' => false, 'error' => 'WEBP images are not supported on this server. Please upload JPG, PNG, or PDF.'];
        }
        return ['path' => null, 'cleanup' => false, 'error' => 'Invalid image file.'];
    }

    if (function_exists('imagealphablending')) {
        @imagealphablending($image, false);
    }
    if (function_exists('imagesavealpha')) {
        @imagesavealpha($image, true);
    }

    $tempBase = @tempnam(sys_get_temp_dir(), 'drimg_');
    if ($tempBase === false) {
        @imagedestroy($image);
        return ['path' => null, 'cleanup' => false, 'error' => 'Failed to prepare image conversion on the server.'];
    }

    $pngPath = $tempBase . '.png';
    @unlink($tempBase);
    if (!@imagepng($image, $pngPath)) {
        @imagedestroy($image);
        @unlink($pngPath);
        return ['path' => null, 'cleanup' => false, 'error' => 'Failed to convert uploaded image to PDF format.'];
    }

    @imagedestroy($image);
    return ['path' => $pngPath, 'cleanup' => true, 'error' => null];
}

function dr_convert_upload_to_pdf(array $file, string $folder, int $index = 1): array {
    $errorCode = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode === UPLOAD_ERR_NO_FILE) {
        return ['path' => null, 'error' => null];
    }
    $validationError = app_upload_validate_file($file, 'resident', 'Attachment');
    if ($validationError !== null) {
        return ['path' => null, 'error' => $validationError];
    }

    $orig = (string)($file['name'] ?? '');
    $tmp = (string)($file['tmp_name'] ?? '');
    if ($orig === '' || !is_uploaded_file($tmp)) {
        return ['path' => null, 'error' => 'Upload validation failed. Please reselect the file and submit again.'];
    }

    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'pdf'], true)) {
        return ['path' => null, 'error' => 'Unsupported file type.'];
    }
    if (!dr_ensure_pdf_tools()) {
        return ['path' => null, 'error' => 'PDF tools are unavailable on the server.'];
    }

    $baseDir = realpath(__DIR__ . '/../../');
    if ($baseDir === false) {
        return ['path' => null, 'error' => 'Server path resolution failed. Please try again later.'];
    }

    $targetDir = $baseDir . '/UnifiedFileAttachment/' . trim($folder, '/');
    if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        return ['path' => null, 'error' => 'Server storage is unavailable. Please try again later.'];
    }
    @chmod($targetDir, 0775);
    $probe = $targetDir . '/.__w_' . bin2hex(random_bytes(4)) . '.tmp';
    $probeOk = @file_put_contents($probe, 'ok');
    if ($probeOk === false) {
        error_log('[documentRequestWorkflow][convert_upload_to_pdf] target dir not writable: ' . $targetDir);
        return ['path' => null, 'error' => 'Upload folder is not writable on the server. Please contact admin.'];
    }
    @unlink($probe);

    $targetName = date('YmdHis') . '_' . $index . '_' . bin2hex(random_bytes(6)) . '.pdf';
    $targetPath = $targetDir . '/' . $targetName;
    $normalizedImagePath = null;

    try {
        if ($ext === 'pdf') {
            if (!dr_move_uploaded_file_to_path($tmp, $targetPath)) {
                $lastError = error_get_last();
                error_log('[documentRequestWorkflow][convert_upload_to_pdf] save pdf failed tmp=' . $tmp . ' target=' . $targetPath . ' err=' . ($lastError['message'] ?? 'unknown'));
                return ['path' => null, 'error' => 'Failed to save uploaded PDF. Please try again.'];
            }
        } else {
            $normalized = dr_normalize_image_for_pdf($tmp, $ext);
            if (!empty($normalized['error'])) {
                return ['path' => null, 'error' => (string)$normalized['error']];
            }
            $normalizedImagePath = (string)($normalized['path'] ?? '');
            $imageTypeForPdf = ($normalizedImagePath !== '' && $normalizedImagePath !== $tmp) ? 'png' : $ext;
            $pdf = new \setasign\Fpdi\Fpdi();
            dr_append_image_page_to_pdf($pdf, $normalizedImagePath !== '' ? $normalizedImagePath : $tmp, $imageTypeForPdf);
            $pdf->Output('F', $targetPath);
        }
    } catch (Throwable $e) {
        @unlink($targetPath);
        error_log('[documentRequestWorkflow][convert_upload_to_pdf] ' . $e->getMessage());
        $message = trim((string)$e->getMessage());
        if ($message === '') {
            $message = 'Failed to convert uploaded file to PDF.';
        }
        return ['path' => null, 'error' => $message];
    } finally {
        if ($normalizedImagePath !== null && $normalizedImagePath !== '') {
            @unlink($normalizedImagePath);
        }
    }

    if (!is_file($targetPath) || filesize($targetPath) <= 0) {
        @unlink($targetPath);
        return ['path' => null, 'error' => 'Converted PDF is empty. Please re-upload the file.'];
    }

    @chmod($targetPath, 0664);
    return ['path' => '/UnifiedFileAttachment/' . trim($folder, '/') . '/' . $targetName, 'error' => null];
}

function dr_collect_pdf_upload_paths(array $files, string $folder, string $label, bool $required = true, int $maxFiles = 3): array {
    $paths = [];

    if (!isset($files['name']) || !is_array($files['name'])) {
        if ($required) {
            return ['paths' => [], 'error' => 'At least one ' . strtolower($label) . ' attachment is required.'];
        }
        return ['paths' => [], 'error' => null];
    }

    $fileCount = min($maxFiles, count($files['name']));
    for ($i = 0; $i < $fileCount; $i++) {
        $entry = [
            'name' => $files['name'][$i] ?? '',
            'type' => $files['type'][$i] ?? '',
            'tmp_name' => $files['tmp_name'][$i] ?? '',
            'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$i] ?? 0,
        ];
        $converted = dr_convert_upload_to_pdf($entry, $folder, $i + 1);
        if (!empty($converted['error'])) {
            return ['paths' => [], 'error' => $label . ' attachment ' . ($i + 1) . ': ' . $converted['error']];
        }
        if (!empty($converted['path'])) {
            $paths[] = (string)$converted['path'];
        }
    }

    if ($required && !$paths) {
        return ['paths' => [], 'error' => 'At least one ' . strtolower($label) . ' attachment is required.'];
    }

    return ['paths' => $paths, 'error' => null];
}

function dr_document_type_token(string $value): string {
    return preg_replace('/[^a-z0-9]+/', '', strtolower(trim($value)));
}

function dr_build_business_clearance_address(array $source): string {
    $sameAsOwner = strtolower(trim((string)($source['business_same_address'] ?? '')));
    if (in_array($sameAsOwner, ['1', 'true', 'yes', 'on'], true)) {
        return trim((string)($source['owner_full_address'] ?? $source['full_address'] ?? ''));
    }

    $system = strtolower(trim((string)($source['business_address_system'] ?? '')));
    $parts = [];

    if ($system === 'lot_block') {
        $lotNumber = trim((string)($source['business_lot_number'] ?? ''));
        $blockNumber = trim((string)($source['business_block_number'] ?? ''));
        $phaseNumber = trim((string)($source['business_phase_number'] ?? ''));
        $subdivision = trim((string)($source['business_subdivision_block'] ?? ''));

        if ($lotNumber === '' && $blockNumber === '' && $phaseNumber === '') {
            return '';
        }

        if ($lotNumber !== '') {
            $parts[] = 'Lot ' . $lotNumber;
        }
        if ($blockNumber !== '') {
            $parts[] = 'Blk ' . $blockNumber;
        }
        if ($phaseNumber !== '') {
            $parts[] = 'Phase ' . $phaseNumber;
        }
        if ($subdivision !== '') {
            $parts[] = $subdivision;
        }
    } else {
        $unitNumber = trim((string)($source['business_unit_number'] ?? ''));
        if ($unitNumber !== '') {
            $parts[] = 'Unit ' . $unitNumber;
        }

        $streetLine = trim(implode(' ', array_filter([
            trim((string)($source['business_street_number'] ?? $source['business_house_number'] ?? '')),
            trim((string)($source['business_street_name'] ?? ''))
        ], static fn($v) => $v !== '')));
        if ($streetLine !== '') {
            $parts[] = $streetLine;
        } else {
            return '';
        }

        $subdivision = trim((string)($source['business_subdivision'] ?? ''));
        if ($subdivision !== '') {
            $parts[] = $subdivision;
        }
    }

    foreach (['business_barangay', 'business_city', 'business_province'] as $key) {
        $value = trim((string)($source[$key] ?? ''));
        if ($value !== '') {
            $parts[] = $value;
        }
    }

    return implode(', ', $parts);
}

function dr_build_general_permit_location(array $source, string $fallback = ''): string {
    $direct = trim((string)($source['location'] ?? $source['lot_full_address'] ?? $source['project_location'] ?? ''));
    if ($direct !== '') {
        return $direct;
    }

    $sameAddress = strtolower(trim((string)($source['lot_same_address'] ?? '')));
    $applicantAddress = trim((string)($source['applicant_full_address'] ?? $source['owner_full_address'] ?? $source['full_address'] ?? ''));
    if (in_array($sameAddress, ['1', 'true', 'yes', 'on'], true)) {
        return $applicantAddress !== '' ? $applicantAddress : $fallback;
    }

    $system = strtolower(trim((string)($source['lot_address_system'] ?? '')));
    $parts = [];

    if ($system === 'lot_block') {
        $lotNumber = trim((string)($source['lot_number'] ?? ''));
        $blockNumber = trim((string)($source['block_number'] ?? ''));
        $phaseNumber = trim((string)($source['lot_phase_number'] ?? ''));
        $subdivision = trim((string)($source['lot_subdivision'] ?? $source['lot_subdivision_block'] ?? ''));

        if ($lotNumber !== '') {
            $parts[] = 'Lot ' . $lotNumber;
        }
        if ($blockNumber !== '') {
            $parts[] = 'Blk ' . $blockNumber;
        }
        if ($phaseNumber !== '') {
            $parts[] = 'Phase ' . $phaseNumber;
        }
        if ($subdivision !== '') {
            $parts[] = $subdivision;
        }
    } elseif ($system === 'house') {
        $unitNumber = trim((string)($source['lot_unit_number'] ?? ''));
        $streetNumber = trim((string)($source['lot_street_number'] ?? ''));
        $streetName = trim((string)($source['lot_street_name'] ?? ''));
        $subdivision = trim((string)($source['lot_subdivision'] ?? ''));

        if ($unitNumber !== '') {
            $parts[] = 'Unit ' . $unitNumber;
        }

        $streetLine = trim(implode(' ', array_filter([$streetNumber, $streetName], static fn($v) => $v !== '')));
        if ($streetLine !== '') {
            $parts[] = $streetLine;
        }
        if ($subdivision !== '') {
            $parts[] = $subdivision;
        }
    }

    foreach (['lot_barangay', 'lot_city', 'lot_province'] as $key) {
        $value = trim((string)($source[$key] ?? ''));
        if ($value !== '') {
            $parts[] = $value;
        }
    }

    $location = implode(', ', $parts);
    if ($location !== '') {
        return $location;
    }
    if ($applicantAddress !== '') {
        return $applicantAddress;
    }
    return $fallback;
}

function dr_fetch_resident_birth_snapshot(mysqli $conn, string $residentId, string $userId): array {
    $sql = "
        SELECT birthdate, birthplace
        FROM residentinformationtbl
        WHERE resident_id = ? OR user_id = ?
        ORDER BY CASE WHEN resident_id = ? THEN 0 ELSE 1 END
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['birthdate' => '', 'birthplace' => ''];
    }
    $stmt->bind_param('sss', $residentId, $userId, $residentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $row = pii_decrypt_resident_row($row) ?? $row;

    return [
        'birthdate' => trim((string)($row['birthdate'] ?? '')),
        'birthplace' => trim((string)($row['birthplace'] ?? '')),
    ];
}

function dr_fetch_resident_barangay_id_snapshot(mysqli $conn, string $residentId, string $userId): array {
    $sql = "
        SELECT
            r.birthdate,
            r.birthplace,
            r.sex,
            e.address AS emergency_address
        FROM residentinformationtbl r
        LEFT JOIN emergencycontacttbl e
            ON e.user_id = r.user_id
        WHERE r.resident_id = ? OR r.user_id = ?
        ORDER BY CASE WHEN r.resident_id = ? THEN 0 ELSE 1 END
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [
            'birthdate' => '',
            'birthplace' => '',
            'sex' => '',
            'emergency_address' => '',
        ];
    }
    $stmt->bind_param('sss', $residentId, $userId, $residentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $row = pii_decrypt_resident_row($row) ?? $row;
    $row = pii_decrypt_assoc($row, ['emergency_address']);

    return [
        'birthdate' => trim((string)($row['birthdate'] ?? '')),
        'birthplace' => trim((string)($row['birthplace'] ?? '')),
        'sex' => trim((string)($row['sex'] ?? '')),
        'emergency_address' => trim((string)($row['emergency_address'] ?? '')),
    ];
}

function dr_has_column(mysqli $conn, string $table, string $column): bool {
    static $cache = [];
    $tableEsc = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($tableEsc === '') {
        return false;
    }
    $key = strtolower($tableEsc . '|' . $column);
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $colEsc = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM {$tableEsc} LIKE '{$colEsc}'");
    $exists = $res instanceof mysqli_result && $res->num_rows > 0;
    $cache[$key] = $exists;
    return $exists;
}

function dr_column_type(mysqli $conn, string $table, string $column): string {
    $tableEsc = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $colEsc = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM {$tableEsc} LIKE '{$colEsc}'");
    if ($res instanceof mysqli_result) {
        $row = $res->fetch_assoc();
        if ($row && isset($row['Type'])) {
            return strtolower((string)$row['Type']);
        }
    }
    return '';
}

function dr_get_fee_map_for_document_types(mysqli $conn, array $documentTypes): array {
    $out = [];
    $clean = [];
    foreach ($documentTypes as $docType) {
        $doc = trim((string)$docType);
        if ($doc === '' || !dr_is_issuance_document_type($doc)) {
            continue;
        }
        $clean[$doc] = true;
    }
    if (!$clean) {
        return $out;
    }
    if (!dr_table_exists($conn, 'documenttypelookuptbl') || !dr_table_exists($conn, 'generalfeestbl')) {
        return $out;
    }

    $names = array_keys($clean);
    $placeholders = implode(',', array_fill(0, count($names), '?'));
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
    $types = str_repeat('s', count($names));
    $bindParams = [$types];
    foreach ($names as $k => $v) {
        $bindParams[] = &$names[$k];
    }
    call_user_func_array([$stmt, 'bind_param'], $bindParams);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $name = trim((string)($row['document_type_name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $amount = $row['amount'];
        $out[$name] = ($amount === null ? null : (float)$amount);
    }
    $stmt->close();
    return $out;
}

function dr_request_details_requires_json(mysqli $conn): bool {
    $colType = dr_column_type($conn, 'documentrequesttbl', 'request_details');
    if (strpos($colType, 'json') !== false) {
        return true;
    }

    $res = $conn->query("
        SELECT cc.CHECK_CLAUSE
        FROM information_schema.CHECK_CONSTRAINTS cc
        JOIN information_schema.TABLE_CONSTRAINTS tc
          ON cc.CONSTRAINT_SCHEMA = tc.CONSTRAINT_SCHEMA
         AND cc.CONSTRAINT_NAME = tc.CONSTRAINT_NAME
        WHERE tc.TABLE_SCHEMA = DATABASE()
          AND tc.TABLE_NAME = 'documentrequesttbl'
          AND tc.CONSTRAINT_TYPE = 'CHECK'
          AND cc.CHECK_CLAUSE LIKE '%request_details%'
    ");
    if (!($res instanceof mysqli_result)) {
        $res = null;
    }
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            $clause = strtolower((string)($row['CHECK_CLAUSE'] ?? ''));
            if (strpos($clause, 'json_valid') !== false) {
                return true;
            }
        }
    }

    // Fallback for servers that don't expose CHECK_CONSTRAINTS reliably.
    $res = $conn->query("SHOW CREATE TABLE documentrequesttbl");
    if ($res instanceof mysqli_result) {
        $row = $res->fetch_assoc();
        $createSql = strtolower((string)($row['Create Table'] ?? $row['Create Table'] ?? ''));
        if (strpos($createSql, 'request_details') !== false && strpos($createSql, 'json_valid') !== false) {
            return true;
        }
    }

    return false;
}

function dr_request_details_token(string $documentTypeRaw, string $documentTypeNormalized): string {
    $raw = strtolower(trim($documentTypeRaw));
    if ($raw !== '') {
        return preg_replace('/[^a-z0-9]+/', '', $raw);
    }

    $map = [
        'certificate of cohabitation' => 'cohabitation',
        'certificate of indigency' => 'indigency',
        'certificateofindigency' => 'indigency',
        'first time job seeker certificate' => 'firsttimejobseeker',
        'certificate of identity' => 'identity',
        'certificate of residency' => 'residency',
        'certificate of good moral' => 'goodmoral',
    ];
    $key = strtolower(trim($documentTypeNormalized));
    if (isset($map[$key])) {
        return $map[$key];
    }
    return preg_replace('/[^a-z0-9]+/', '', $key);
}

function dr_request_id_is_numeric(mysqli $conn): bool {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $res = $conn->query("SHOW COLUMNS FROM documentrequesttbl LIKE 'request_id'");
    if (!($res instanceof mysqli_result)) {
        $cached = false;
        return false;
    }
    $row = $res->fetch_assoc();
    $type = strtolower((string)($row['Type'] ?? ''));
    $cached = strpos($type, 'int') !== false;
    return $cached;
}

function dr_pick_any_status_id(mysqli $conn, array $preferred = []): ?int {
    foreach ($preferred as $name) {
        $sid = dr_find_status_id($conn, $name, ['DocumentRequest', 'Transaction', 'DocumentVerification', 'ResidentDocumentProfiling']);
        if ($sid !== null) {
            return $sid;
        }
    }
    $res = $conn->query("SELECT status_id FROM statuslookuptbl ORDER BY status_id ASC LIMIT 1");
    if ($res instanceof mysqli_result) {
        $row = $res->fetch_assoc();
        if ($row && isset($row['status_id'])) {
            return (int)$row['status_id'];
        }
    }
    return null;
}

function dr_pick_doc_type_id(mysqli $conn, string $documentType): ?int {
    $stmt = $conn->prepare("SELECT document_type_id FROM documenttypelookuptbl WHERE document_type_name = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $documentType);
        $stmt->execute();
        $stmt->bind_result($id);
        if ($stmt->fetch()) {
            $stmt->close();
            return (int)$id;
        }
        $stmt->close();
    }

    $res = $conn->query("SELECT document_type_id FROM documenttypelookuptbl ORDER BY document_type_id ASC LIMIT 1");
    if ($res instanceof mysqli_result) {
        $row = $res->fetch_assoc();
        if ($row && isset($row['document_type_id'])) {
            return (int)$row['document_type_id'];
        }
    }
    return null;
}

function dr_get_resident_name_parts(mysqli $conn, string $userId): array {
    $stmt = $conn->prepare("SELECT lastname, firstname, middlename, suffix FROM residentinformationtbl WHERE user_id = ? LIMIT 1");
    if (!$stmt) {
        return ['lastname' => '', 'firstname' => '', 'middlename' => '', 'suffix' => ''];
    }
    $stmt->bind_param('s', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return [
        'lastname' => (string)($row['lastname'] ?? ''),
        'firstname' => (string)($row['firstname'] ?? ''),
        'middlename' => (string)($row['middlename'] ?? ''),
        'suffix' => (string)($row['suffix'] ?? ''),
    ];
}

function dr_ensure_attachment_status_id(mysqli $conn, ?int $preferredStatusId = null): ?int {
    if ($preferredStatusId !== null && $preferredStatusId > 0) {
        return $preferredStatusId;
    }

    $statusId = dr_pick_any_status_id($conn, ['PendingVerification', 'PendingReview', 'Pending']);
    if ($statusId !== null) {
        return $statusId;
    }

    // Minimal bootstrap fallback when status lookups were cleared.
    $statusName = 'PendingVerification';
    $statusType = 'DocumentRequest';
    $insertId = 0;
    $sql = "INSERT INTO statuslookuptbl (status_name, status_type) VALUES (?, ?)";
    $ins = $conn->prepare($sql);
    if ($ins) {
        $ins->bind_param('ss', $statusName, $statusType);
        $ok = $ins->execute();
        $insertId = $ok ? (int)$ins->insert_id : 0;
        $ins->close();
    }
    if ($insertId > 0) {
        return $insertId;
    }

    return dr_find_status_id($conn, $statusName, [$statusType]) ?? dr_find_status_id($conn, $statusName, []);
}

function dr_create_request_attachment(
    mysqli $conn,
    string $residentId,
    string $userId,
    string $documentType,
    string $payloadJson,
    ?int $documentTypeId = null,
    ?int $statusId = null
): ?int {
    $docTypeId = $documentTypeId;
    if ($docTypeId === null || $docTypeId <= 0) {
        $docTypeId = dr_get_or_create_document_type_id($conn, $documentType, 'DocumentRequest')
            ?? dr_pick_doc_type_id($conn, $documentType);
    }
    $statusId = dr_ensure_attachment_status_id($conn, $statusId);
    if ($docTypeId === null || $docTypeId <= 0 || $statusId === null || $statusId <= 0) {
        return null;
    }

    $baseDir = realpath(__DIR__ . '/../../');
    if ($baseDir === false) {
        return null;
    }
    $safeResident = preg_replace('/[^A-Za-z0-9_-]/', '', $residentId);
    if ($safeResident === '') {
        $safeResident = 'resident';
    }
    $folder = $baseDir . '/UnifiedFileAttachment/DocumentRequests/' . $safeResident;
    if (!is_dir($folder)) {
        if (!@mkdir($folder, 0775, true) && !is_dir($folder)) {
            error_log('[documentRequestWorkflow][attachment] failed to create folder: ' . $folder);
            return null;
        }
    }

    $fileName = 'request_' . date('YmdHis') . '_' . bin2hex(random_bytes(5)) . '.json';
    $diskPath = $folder . '/' . $fileName;
    if (@file_put_contents($diskPath, $payloadJson) === false) {
        error_log('[documentRequestWorkflow][attachment] failed to write file: ' . $diskPath);
        return null;
    }
    $webPath = '/UnifiedFileAttachment/DocumentRequests/' . $safeResident . '/' . $fileName;

    $fileType = 'json';
    $remarks = 'document_request_payload';
    $idNumber = null;
    $sourceType = 'DocumentRequest';
    $sourceId = $residentId;
    try {
        $insertId = insertUnifiedFileAttachment($conn, [
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'document_type_id' => $docTypeId,
            'file_name' => $fileName,
            'file_path' => $webPath,
            'file_type' => $fileType,
            'user_id_uploaded_by' => $userId,
            'status_id_verify' => $statusId,
            'remarks' => $remarks,
            'id_number' => $idNumber,
        ], 'document request payload');
    } catch (Throwable $e) {
        error_log('[documentRequestWorkflow][attachment] insert failed: ' . $e->getMessage());
        $insertId = 0;
    }

    if ($insertId <= 0 && file_exists($diskPath)) {
        @unlink($diskPath);
    }
    return $insertId > 0 ? $insertId : null;
}

if ($action === 'submit_request') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        dr_respond_json(405, ['success' => false, 'message' => 'Method not allowed.']);
    }

    $documentTypeRaw = (string)($_POST['document_type'] ?? '');
    $documentType = dr_normalize_document_type($documentTypeRaw);
    if ($documentType === '') {
        $documentType = 'Certificate Request';
    }
    $documentTypeId = dr_get_or_create_document_type_id($conn, $documentType, 'DocumentRequest');

    $purpose = trim((string)($_POST['request_purpose'] ?? $_POST['purpose'] ?? ''));
    if ($purpose === '') {
        $purpose = trim((string)($_POST['request_officer'] ?? ''));
    }
    if (strtolower(trim($documentType)) === 'certificate of residency') {
        $purpose = 'Residency Verification';
    }

    $documentTypeToken = dr_document_type_token($documentTypeRaw !== '' ? $documentTypeRaw : $documentType);
    $isBarangayIdRequest = dr_is_barangay_id_document_type($documentTypeRaw !== '' ? $documentTypeRaw : $documentType);
    if ($isBarangayIdRequest) {
        $barangayIdState = dr_resident_barangay_id_state($conn, $residentForeignId, $residentId);
        if (!($barangayIdState['can_submit_new_request'] ?? false)) {
            $message = 'You cannot submit a new Barangay ID request right now.';
            if (($barangayIdState['block_reason'] ?? '') === 'pending_request') {
                $message = 'You already have an active Barangay ID request in progress.';
            } elseif (($barangayIdState['block_reason'] ?? '') === 'active_valid') {
                $message = 'Your current Barangay ID is still valid. You may renew it three months before expiry or request a replacement only after tagging it as lost.';
            }
            if (dr_wants_html_redirect()) {
                dr_redirect_to_barangay_id_landing('request_not_allowed');
            }
            dr_respond_json(422, ['success' => false, 'message' => $message]);
        }

        $requestedMode = dr_normalize_barangay_id_request_mode((string)($_POST['barangay_id_request_mode'] ?? ''));
        $expectedMode = dr_normalize_barangay_id_request_mode((string)($barangayIdState['submission_mode'] ?? 'new'));
        if ($requestedMode !== $expectedMode) {
            if (dr_wants_html_redirect()) {
                dr_redirect_to_barangay_id_landing('request_not_allowed');
            }
            dr_respond_json(422, ['success' => false, 'message' => 'Barangay ID request type is no longer available. Please reload the page and try again.']);
        }

        $sourceRequestId = trim((string)($_POST['barangay_id_source_request_id'] ?? ''));
        $latestCompletedRequestId = trim((string)($barangayIdState['latest_completed_request_id'] ?? ''));
        if ($expectedMode !== 'new') {
            if ($latestCompletedRequestId === '') {
                if (dr_wants_html_redirect()) {
                    dr_redirect_to_barangay_id_landing('request_not_allowed');
                }
                dr_respond_json(422, ['success' => false, 'message' => 'No previous Barangay ID record was found for this request.']);
            }
            if ($sourceRequestId !== '' && $sourceRequestId !== $latestCompletedRequestId) {
                if (dr_wants_html_redirect()) {
                    dr_redirect_to_barangay_id_landing('request_not_allowed');
                }
                dr_respond_json(422, ['success' => false, 'message' => 'This Barangay ID request is no longer tied to the current active record.']);
            }
            $_POST['barangay_id_source_request_id'] = $latestCompletedRequestId;
        } else {
            $_POST['barangay_id_source_request_id'] = '';
        }
        $_POST['barangay_id_request_mode'] = $expectedMode;

        $residentBarangayIdSnapshot = dr_fetch_resident_barangay_id_snapshot($conn, $residentId, $residentForeignId);
        if (trim((string)($_POST['birthdate'] ?? '')) === '' && $residentBarangayIdSnapshot['birthdate'] !== '') {
            $_POST['birthdate'] = $residentBarangayIdSnapshot['birthdate'];
        }
        if (trim((string)($_POST['birthplace'] ?? '')) === '' && $residentBarangayIdSnapshot['birthplace'] !== '') {
            $_POST['birthplace'] = $residentBarangayIdSnapshot['birthplace'];
        }
        if (trim((string)($_POST['sex'] ?? $_POST['gender'] ?? '')) === '' && $residentBarangayIdSnapshot['sex'] !== '') {
            $_POST['sex'] = $residentBarangayIdSnapshot['sex'];
        }
        if (trim((string)($_POST['emergency_address'] ?? '')) === '' && $residentBarangayIdSnapshot['emergency_address'] !== '') {
            $_POST['emergency_address'] = $residentBarangayIdSnapshot['emergency_address'];
        }

        $contactNumber = trim((string)($_POST['contact_number'] ?? $_POST['phone_number'] ?? ''));
        if ($contactNumber === '') {
            dr_respond_json(422, ['success' => false, 'message' => 'Contact number is required.']);
        }
        $_POST['contact_number'] = $contactNumber;
        if (trim((string)($_POST['phone_number'] ?? '')) === '') {
            $_POST['phone_number'] = $contactNumber;
        }

        $fullAddress = trim((string)($_POST['full_address'] ?? $_POST['full_address_display'] ?? ''));
        if ($fullAddress === '') {
            $fullAddress = implode(', ', array_filter([
                trim((string)($_POST['unitNumber'] ?? '')) !== '' ? 'Unit ' . trim((string)($_POST['unitNumber'] ?? '')) : '',
                trim(implode(' ', array_filter([
                    trim((string)($_POST['houseNumber'] ?? '')),
                    trim((string)($_POST['streetName'] ?? '')),
                ], static fn($v) => $v !== ''))),
                trim((string)($_POST['subdivision'] ?? '')) !== '' ? trim((string)($_POST['subdivision'] ?? '')) . ' Subdivision' : '',
                trim((string)($_POST['areaNumber'] ?? '')) !== '' ? 'Area ' . trim((string)($_POST['areaNumber'] ?? '')) : '',
                'San Jose',
                'Rodriguez',
                'Rizal',
            ], static fn($part) => trim((string)$part) !== ''));
        }
        if ($fullAddress === '') {
            dr_respond_json(422, ['success' => false, 'message' => 'Full address is required.']);
        }
        $_POST['full_address'] = $fullAddress;
        $_POST['full_address_display'] = $fullAddress;

        $requiredFields = [
            'last_name' => 'Last name is required.',
            'first_name' => 'First name is required.',
            'birthdate' => 'Birthdate is required.',
            'birthplace' => 'Birthplace is required.',
            'emergency_last' => 'Emergency contact last name is required.',
            'emergency_first' => 'Emergency contact first name is required.',
            'emergency_contact' => 'Emergency contact number is required.',
        ];
        foreach ($requiredFields as $field => $message) {
            if (trim((string)($_POST[$field] ?? '')) === '') {
                dr_respond_json(422, ['success' => false, 'message' => $message]);
            }
        }

        $purposeText = dr_barangay_id_purpose_for_mode($expectedMode);
        $_POST['request_purpose'] = $purposeText;
        $_POST['purpose'] = $purposeText;
    }

    $isBusinessPermitClearanceRequest = in_array($documentTypeToken, [
        'barangayclearanceforbusinesspermit',
        'barangaybusinessclearance',
        'businessclearance'
    ], true);
    if ($isBusinessPermitClearanceRequest) {
        $applicationType = trim((string)($_POST['application_type'] ?? ''));
        if (!in_array($applicationType, ['New', 'Renewal'], true)) {
            dr_respond_json(422, ['success' => false, 'message' => 'Application type is required.']);
        }

        $ownerType = trim((string)($_POST['owner_type'] ?? ''));
        if (!in_array($ownerType, ['Owner', 'Renter'], true)) {
            dr_respond_json(422, ['success' => false, 'message' => 'Ownership is required.']);
        }
        if ($ownerType === 'Renter') {
            $renterOwnerLastName = trim((string)($_POST['ro_ln'] ?? ''));
            $renterOwnerFirstName = trim((string)($_POST['ro_fn'] ?? ''));
            if ($renterOwnerLastName === '' || $renterOwnerFirstName === '') {
                dr_respond_json(422, ['success' => false, 'message' => 'Owner name is required for renter applications.']);
            }
        }

        $businessName = trim((string)($_POST['business_name'] ?? $_POST['b_name'] ?? ''));
        if ($businessName === '') {
            dr_respond_json(422, ['success' => false, 'message' => 'Business name is required.']);
        }
        $_POST['business_name'] = $businessName;

        $businessType = trim((string)($_POST['business_type'] ?? ''));
        if ($businessType === '') {
            dr_respond_json(422, ['success' => false, 'message' => 'Nature / Type of Business is required.']);
        }
        $_POST['business_type'] = $businessType;

        $initialOperationDate = trim((string)($_POST['initial_operation_date'] ?? $_POST['b_date'] ?? ''));
        if ($initialOperationDate === '') {
            dr_respond_json(422, ['success' => false, 'message' => 'Date of initial operation is required.']);
        }
        $_POST['initial_operation_date'] = $initialOperationDate;

        $businessContact = trim((string)($_POST['business_contact_number'] ?? $_POST['b_contact_1'] ?? ''));
        if ($businessContact !== '') {
            $_POST['business_contact_number'] = $businessContact;
        }

        $businessFullAddress = trim((string)($_POST['business_full_address'] ?? ''));
        if ($businessFullAddress === '') {
            $businessFullAddress = dr_build_business_clearance_address($_POST);
        }
        if ($businessFullAddress === '') {
            dr_respond_json(422, ['success' => false, 'message' => 'Business address is required.']);
        }
        if ($businessFullAddress !== '') {
            $_POST['business_full_address'] = $businessFullAddress;
            $_POST['location'] = $businessFullAddress;
        }

        $ownerAddress = trim((string)($_POST['owner_full_address'] ?? $_POST['full_address'] ?? ''));
        if ($ownerAddress !== '') {
            $_POST['full_address'] = $ownerAddress;
        }

        $residentBirthSnapshot = dr_fetch_resident_birth_snapshot($conn, $residentId, $residentForeignId);
        if (trim((string)($_POST['birthdate'] ?? '')) === '' && $residentBirthSnapshot['birthdate'] !== '') {
            $_POST['birthdate'] = $residentBirthSnapshot['birthdate'];
        }
        if (trim((string)($_POST['birthplace'] ?? '')) === '' && $residentBirthSnapshot['birthplace'] !== '') {
            $_POST['birthplace'] = $residentBirthSnapshot['birthplace'];
        }

        $purposeText = $applicationType === 'Renewal' ? 'Business Permit - Renewal' : 'Business Permit - New Application';
        if (trim((string)($_POST['request_purpose'] ?? '')) === '') {
            $_POST['request_purpose'] = $purposeText;
        }
        if (trim((string)($_POST['purpose'] ?? '')) === '') {
            $_POST['purpose'] = $purposeText;
        }

        $uploadSets = $applicationType === 'Renewal'
            ? [
                [
                    'field' => 'renewal_business_reg_files',
                    'folder' => 'DocumentRequests/BusinessClearance/BusinessRegistrations',
                    'label' => 'Updated business registration',
                    'path_field' => 'renewal_business_reg_file_path',
                    'paths_field' => 'renewal_business_reg_file_paths',
                ],
                [
                    'field' => 'renewal_proof_address_files',
                    'folder' => 'DocumentRequests/BusinessClearance/ProofOfAddress',
                    'label' => 'Updated proof of business address',
                    'path_field' => 'renewal_proof_address_file_path',
                    'paths_field' => 'renewal_proof_address_file_paths',
                ],
                [
                    'field' => 'renewal_business_photo_files',
                    'folder' => 'DocumentRequests/BusinessClearance/BusinessPhotos',
                    'label' => 'Updated picture of establishment or business',
                    'path_field' => 'renewal_business_photo_file_path',
                    'paths_field' => 'renewal_business_photo_file_paths',
                ],
            ]
            : [
                [
                    'field' => 'business_reg_files',
                    'folder' => 'DocumentRequests/BusinessClearance/BusinessRegistrations',
                    'label' => 'Business registration',
                    'path_field' => 'business_reg_file_path',
                    'paths_field' => 'business_reg_file_paths',
                ],
                [
                    'field' => 'proof_address_files',
                    'folder' => 'DocumentRequests/BusinessClearance/ProofOfAddress',
                    'label' => 'Proof of business address',
                    'path_field' => 'proof_address_file_path',
                    'paths_field' => 'proof_address_file_paths',
                ],
                [
                    'field' => 'business_photo_files',
                    'folder' => 'DocumentRequests/BusinessClearance/BusinessPhotos',
                    'label' => 'Picture of establishment or business',
                    'path_field' => 'business_photo_file_path',
                    'paths_field' => 'business_photo_file_paths',
                ],
            ];

        $requiredFieldMap = $applicationType === 'Renewal'
            ? [
                'renewal_business_reg_type' => 'Updated business registration type is required.',
                'renewal_proof_address_type' => 'Updated proof of business address type is required.',
            ]
            : [
                'business_reg_type' => 'Business registration type is required.',
                'proof_address_type' => 'Proof of business address type is required.',
            ];
        foreach ($requiredFieldMap as $field => $message) {
            if (trim((string)($_POST[$field] ?? '')) === '') {
                dr_respond_json(422, ['success' => false, 'message' => $message]);
            }
        }

        $proofAddressTypeField = $applicationType === 'Renewal' ? 'renewal_proof_address_type' : 'proof_address_type';
        $proofAddressNumberField = $applicationType === 'Renewal' ? 'renewal_proof_address_number' : 'proof_address_number';
        if (trim((string)($_POST[$proofAddressTypeField] ?? '')) !== 'lease'
            && trim((string)($_POST[$proofAddressNumberField] ?? '')) === '') {
            dr_respond_json(422, ['success' => false, 'message' => 'Proof of business address document number is required.']);
        }

        foreach ($uploadSets as $uploadSet) {
            $converted = dr_collect_pdf_upload_paths(
                $_FILES[$uploadSet['field']] ?? [],
                $uploadSet['folder'],
                $uploadSet['label']
            );
            if (!empty($converted['error'])) {
                dr_respond_json(422, ['success' => false, 'message' => (string)$converted['error']]);
            }
            $paths = is_array($converted['paths'] ?? null) ? $converted['paths'] : [];
            $_POST[$uploadSet['paths_field']] = $paths;
            $_POST[$uploadSet['path_field']] = (string)($paths[0] ?? '');
        }
    }

    $isTricyclePermitRequest = in_array($documentTypeToken, [
        'barangayclearancefortricyclepermit',
        'clearancefortricyclepermit',
        'tricyclepermit',
        'fortricyclepermit',
    ], true);
    if ($isTricyclePermitRequest) {
        $applicationType = trim((string)($_POST['application_type'] ?? ''));
        if (!in_array($applicationType, ['New', 'Renewal'], true)) {
            dr_respond_json(422, ['success' => false, 'message' => 'Application type is required.']);
        }

        $vehicleMake = trim((string)($_POST['vehicle_make'] ?? ''));
        $vehicleMakeOther = trim((string)($_POST['vehicle_make_other'] ?? ''));
        $allowedVehicleMakes = ['Rusi', 'Yamaha', 'Kawasaki', 'Honda', 'Others'];
        if (!in_array($vehicleMake, $allowedVehicleMakes, true)) {
            dr_respond_json(422, ['success' => false, 'message' => 'Valid vehicle make is required.']);
        }
        if ($vehicleMake === 'Others') {
            if ($vehicleMakeOther === '') {
                dr_respond_json(422, ['success' => false, 'message' => 'Please specify the vehicle make.']);
            }
            $vehicleMake = $vehicleMakeOther;
        }
        $_POST['vehicle_make'] = $vehicleMake;
        unset($_POST['vehicle_make_other']);

        $franchiseeMatrix = [
            'PRIVATE - FAMILY USE' => ['franchisee' => 'Private - FAMILY USE', 'location' => ''],
            'PRIVATE - DELIVERY USE' => ['franchisee' => 'Private - DELIVERY USE', 'location' => ''],
            'SJ1 - NEW ROTODA' => ['franchisee' => 'SJ1 - NEW ROTODA', 'location' => 'AREA 1'],
            'SJ-1 NEW ROTODA' => ['franchisee' => 'SJ1 - NEW ROTODA', 'location' => 'AREA 1'],
            'SJ2 - SUBTODA' => ['franchisee' => 'SJ2 - SUBTODA', 'location' => 'AREA 2'],
            'SJ-2 SUBTODA' => ['franchisee' => 'SJ2 - SUBTODA', 'location' => 'AREA 2'],
            'SJ3 - BAGONG BUHAY TODA' => ['franchisee' => 'SJ3 - BAGONG BUHAY TODA', 'location' => 'AREA 3'],
            'SJ-3 BAGONG BUHAY TODA' => ['franchisee' => 'SJ3 - BAGONG BUHAY TODA', 'location' => 'AREA 3'],
            'SJ4 - KV1 TODA' => ['franchisee' => 'SJ4 - KV1 TODA', 'location' => 'AREA 4'],
            'SJ-4 KV1 TODA' => ['franchisee' => 'SJ4 - KV1 TODA', 'location' => 'AREA 4'],
            'SJ5 - UPLAND TODA' => ['franchisee' => 'SJ5 - UPLAND TODA', 'location' => 'AREA 5'],
            'SJ-5 UPLAND TODA' => ['franchisee' => 'SJ5 - UPLAND TODA', 'location' => 'AREA 5'],
            'SUBPODA' => ['franchisee' => 'SUBPODA', 'location' => ''],
            'SUB-PODA' => ['franchisee' => 'SUBPODA', 'location' => ''],
            'OTHERS' => ['franchisee' => 'OTHERS', 'location' => null],
        ];
        $franchisee = trim((string)($_POST['franchisee'] ?? $_POST['vehicle_franchise'] ?? ''));
        $franchiseeKey = strtoupper((string)(preg_replace('/\s+/', ' ', $franchisee) ?? $franchisee));
        $franchiseeSelection = $franchiseeMatrix[$franchiseeKey] ?? null;
        if (!is_array($franchiseeSelection)) {
            dr_respond_json(422, ['success' => false, 'message' => 'Valid franchisee is required.']);
        }
        $franchisee = (string)($franchiseeSelection['franchisee'] ?? '');
        $todaPodaLocation = trim((string)($_POST['location_of_toda_poda'] ?? $_POST['location'] ?? ''));
        if (($franchiseeSelection['location'] ?? null) === null) {
            if ($todaPodaLocation === '') {
                dr_respond_json(422, ['success' => false, 'message' => 'Location of TODA / PODA is required for Others.']);
            }
        } else {
            $todaPodaLocation = (string)($franchiseeSelection['location'] ?? '');
        }
        $_POST['franchisee'] = $franchisee;
        $_POST['vehicle_franchise'] = $franchisee;
        $_POST['location_of_toda_poda'] = $todaPodaLocation;
        $_POST['location'] = $todaPodaLocation;

        $vehicleNamedToOwner = strtolower(trim((string)($_POST['vehicle_named_to_owner'] ?? '')));
        if (!in_array($vehicleNamedToOwner, ['yes', 'no'], true)) {
            dr_respond_json(422, ['success' => false, 'message' => 'Please indicate whether the vehicle is named after the owner.']);
        }
        $_POST['vehicle_named_to_owner'] = $vehicleNamedToOwner;

        $tricycleFieldRules = [
            'plate_number' => [
                'required' => 'Plate number is required.',
                'invalid' => 'Plate number must be up to 7 letters and numbers.',
                'pattern' => '/^[A-Za-z0-9]{1,7}$/',
            ],
            'body_number' => [
                'required' => 'Body number is required.',
                'invalid' => 'Body number must be up to 8 letters and numbers.',
                'pattern' => '/^[A-Za-z0-9]{1,8}$/',
            ],
            'chassis_number' => [
                'required' => 'Chassis number is required.',
                'invalid' => 'Chassis number must follow the format XXXX-XXXXXXXXXXX.',
                'pattern' => '/^[A-Za-z0-9]{4}-[A-Za-z0-9]{11}$/',
            ],
            'motor_number' => [
                'required' => 'Motor number is required.',
                'invalid' => 'Motor number must be up to 20 letters and numbers.',
                'pattern' => '/^[A-Za-z0-9]{1,20}$/',
            ],
            'or_number' => [
                'required' => 'O.R. number is required.',
                'invalid' => 'O.R. number must be up to 20 letters and numbers.',
                'pattern' => '/^[A-Za-z0-9]{1,20}$/',
            ],
            'cr_number' => [
                'required' => 'C.R. number is required.',
                'invalid' => 'C.R. number must be up to 20 letters and numbers.',
                'pattern' => '/^[A-Za-z0-9]{1,20}$/',
            ],
        ];
        foreach ($tricycleFieldRules as $field => $rule) {
            $value = strtoupper(trim((string)($_POST[$field] ?? '')));
            if ($value === '') {
                dr_respond_json(422, ['success' => false, 'message' => (string)$rule['required']]);
            }
            if (!preg_match((string)$rule['pattern'], $value)) {
                dr_respond_json(422, ['success' => false, 'message' => (string)$rule['invalid']]);
            }
            $_POST[$field] = $value;
        }

        $saveOptionalTricycleUpload = static function (string $field, string $folder, string $message) {
            $errorCode = (int)(($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE));
            if ($errorCode === UPLOAD_ERR_NO_FILE) {
                return '';
            }

            $upload = dr_save_upload($_FILES[$field] ?? [], $folder, ['jpg', 'jpeg', 'png', 'pdf']);
            if (!empty($upload['error'])) {
                dr_respond_json(422, ['success' => false, 'message' => $message . ': ' . $upload['error']]);
            }
            return (string)($upload['path'] ?? '');
        };

        $requiredUploads = [
            ['field' => 'or_vehicle_file', 'folder' => 'DocumentRequests/TricyclePermit/VehicleOR', 'message' => 'O.R. of the vehicle'],
        ];
        if ($applicationType !== 'Renewal') {
            $requiredUploads[] = ['field' => 'cr_vehicle_file', 'folder' => 'DocumentRequests/TricyclePermit/VehicleCR', 'message' => 'C.R. of the vehicle'];
            $requiredUploads[] = ['field' => 'toda_poda_cert_file', 'folder' => 'DocumentRequests/TricyclePermit/TodaPodaCertification', 'message' => 'TODA / PODA certification'];
        }
        foreach ($requiredUploads as $uploadSet) {
            $upload = dr_save_upload($_FILES[$uploadSet['field']] ?? [], $uploadSet['folder'], ['jpg', 'jpeg', 'png', 'pdf']);
            if (!empty($upload['error'])) {
                dr_respond_json(422, ['success' => false, 'message' => $uploadSet['message'] . ': ' . $upload['error']]);
            }
            $_POST[$uploadSet['field'] . '_path'] = (string)($upload['path'] ?? '');
        }

        if ($applicationType === 'Renewal') {
            $_POST['cr_vehicle_file_path'] = $saveOptionalTricycleUpload(
                'cr_vehicle_file',
                'DocumentRequests/TricyclePermit/VehicleCR',
                'C.R. of the vehicle'
            );
            $_POST['toda_poda_cert_file_path'] = $saveOptionalTricycleUpload(
                'toda_poda_cert_file',
                'DocumentRequests/TricyclePermit/TodaPodaCertification',
                'TODA / PODA certification'
            );
        }

        $_POST['authorization_vehicle_file_path'] = $saveOptionalTricycleUpload(
            'authorization_vehicle_file',
            'DocumentRequests/TricyclePermit/VehicleAuthorization',
            'Authorization of vehicle'
        );

        if ($vehicleNamedToOwner === 'no') {
            if ($applicationType === 'Renewal') {
                $_POST['deed_of_sale_file_path'] = $saveOptionalTricycleUpload(
                    'deed_of_sale_file',
                    'DocumentRequests/TricyclePermit/DeedOfSale',
                    'Notarized deed of sale'
                );
            } else {
                $deedUpload = dr_save_upload($_FILES['deed_of_sale_file'] ?? [], 'DocumentRequests/TricyclePermit/DeedOfSale', ['jpg', 'jpeg', 'png', 'pdf']);
                if (!empty($deedUpload['error'])) {
                    dr_respond_json(422, ['success' => false, 'message' => 'Notarized deed of sale: ' . $deedUpload['error']]);
                }
                $_POST['deed_of_sale_file_path'] = (string)($deedUpload['path'] ?? '');
            }
        } else {
            $_POST['deed_of_sale_file_path'] = '';
        }

        if ($applicationType === 'Renewal') {
            $_POST['last_year_clearance_file_path'] = $saveOptionalTricycleUpload(
                'last_year_clearance_file',
                'DocumentRequests/TricyclePermit/PreviousBarangayClearance',
                'Barangay clearance from previous year'
            );
        } else {
            $_POST['last_year_clearance_file_path'] = '';
        }

        $purposeText = $applicationType === 'Renewal' ? 'Tricycle Permit - Renewal' : 'Tricycle Permit - New Application';
        if (trim((string)($_POST['request_purpose'] ?? '')) === '') {
            $_POST['request_purpose'] = $purposeText;
        }
        if (trim((string)($_POST['purpose'] ?? '')) === '') {
            $_POST['purpose'] = $purposeText;
        }
    }

    $generalPermitConfig = null;
    if (in_array($documentTypeToken, [
        'barangayclearanceforelectricalpermit',
        'clearanceforelectricalpermit',
        'electricalpermit',
    ], true)) {
        $generalPermitConfig = [
            'label' => 'Electrical permit',
            'folder' => 'ElectricalPermit',
            'default_purpose' => 'Electrical Permit Application',
            'allowed_proof_types' => ['lease', 'tct', 'tax_declaration'],
            'requires_ownership_type' => true,
            'requires_sec_certificate' => false,
            'requires_sec_for_ownership' => true,
            'site_photo_label' => 'Picture of establishment / property',
        ];
    } elseif (in_array($documentTypeToken, [
        'barangayclearanceforwaterpermit',
        'clearanceforwaterpermit',
        'waterpermit',
    ], true)) {
        $generalPermitConfig = [
            'label' => 'Water permit',
            'folder' => 'WaterPermit',
            'default_purpose' => 'Water Permit Application',
            'allowed_proof_types' => ['lease', 'tct', 'tax_declaration'],
            'requires_ownership_type' => true,
            'requires_sec_certificate' => false,
            'requires_sec_for_ownership' => true,
            'site_photo_label' => 'Picture of establishment / property',
        ];
    } elseif (in_array($documentTypeToken, [
        'barangayclearanceforresidentialpermit',
        'clearanceforresidentialpermit',
        'residentialpermit',
        'barangayclearanceforresidentialbuildingpermit',
        'clearanceforresidentialbuildingpermit',
        'residentialbuildingpermit',
    ], true)) {
        $generalPermitConfig = [
            'label' => 'Residential permit',
            'folder' => 'ResidentialPermit',
            'default_purpose' => '',
            'allowed_proof_types' => ['tct', 'tax_declaration'],
            'requires_ownership_type' => false,
            'requires_sec_certificate' => false,
            'requires_sec_for_ownership' => false,
            'site_photo_label' => 'Picture of residence / property',
        ];
    } elseif (in_array($documentTypeToken, [
        'barangayclearanceforcommercialpermit',
        'clearanceforcommercialpermit',
        'commercialpermit',
        'barangayclearanceforcommercialbuildingpermit',
        'clearanceforcommercialbuildingpermit',
        'commercialbuildingpermit',
    ], true)) {
        $generalPermitConfig = [
            'label' => 'Commercial permit',
            'folder' => 'CommercialPermit',
            'default_purpose' => '',
            'allowed_proof_types' => ['tct', 'tax_declaration'],
            'requires_ownership_type' => false,
            'requires_sec_certificate' => true,
            'requires_sec_for_ownership' => false,
            'site_photo_label' => 'Picture of establishment / property',
        ];
    }

    if ($generalPermitConfig !== null) {
        $sameAddress = strtolower(trim((string)($_POST['lot_same_address'] ?? '')));
        $usesApplicantAddress = in_array($sameAddress, ['1', 'true', 'yes', 'on'], true);
        $lotAddressSystem = strtolower(trim((string)($_POST['lot_address_system'] ?? '')));

        if (!$usesApplicantAddress) {
            if (!in_array($lotAddressSystem, ['house', 'lot_block'], true)) {
                dr_respond_json(422, ['success' => false, 'message' => 'Lot address system is required.']);
            }

            if ($lotAddressSystem === 'house') {
                if (trim((string)($_POST['lot_street_number'] ?? '')) === '' || trim((string)($_POST['lot_street_name'] ?? '')) === '') {
                    dr_respond_json(422, ['success' => false, 'message' => 'Lot street number and street name are required.']);
                }
            } elseif (
                trim((string)($_POST['lot_number'] ?? '')) === ''
                || trim((string)($_POST['block_number'] ?? '')) === ''
                || trim((string)($_POST['lot_phase_number'] ?? '')) === ''
            ) {
                dr_respond_json(422, ['success' => false, 'message' => 'Lot number, block number, and phase are required.']);
            }
        }

        $applicantAddress = trim((string)($_POST['applicant_full_address'] ?? $_POST['owner_full_address'] ?? $_POST['full_address'] ?? ''));
        if ($applicantAddress !== '') {
            $_POST['full_address'] = $applicantAddress;
        }
        $location = dr_build_general_permit_location($_POST, $applicantAddress);
        if ($location !== '') {
            $_POST['location'] = $location;
            $_POST['project_location'] = $location;
            $_POST['lot_full_address'] = $location;
        }

        if ($generalPermitConfig['default_purpose'] !== '') {
            if (trim((string)($_POST['request_purpose'] ?? '')) === '') {
                $_POST['request_purpose'] = $generalPermitConfig['default_purpose'];
            }
            if (trim((string)($_POST['purpose'] ?? '')) === '') {
                $_POST['purpose'] = $generalPermitConfig['default_purpose'];
            }
        } elseif (trim((string)($_POST['purpose'] ?? '')) === '') {
            dr_respond_json(422, ['success' => false, 'message' => 'Purpose is required.']);
        }

        $ownershipType = trim((string)($_POST['ownership_type'] ?? ''));
        $needsSecCertificate = (bool)$generalPermitConfig['requires_sec_certificate'];
        if ($generalPermitConfig['requires_ownership_type']) {
            if (!in_array($ownershipType, ['Individual', 'Partnership', 'Company'], true)) {
                dr_respond_json(422, ['success' => false, 'message' => 'Ownership type is required.']);
            }
            $needsSecCertificate = $needsSecCertificate || (
                (bool)$generalPermitConfig['requires_sec_for_ownership']
                && in_array($ownershipType, ['Partnership', 'Company'], true)
            );
        }

        $proofAddressType = trim((string)($_POST['proof_address_type'] ?? ''));
        if (!in_array($proofAddressType, $generalPermitConfig['allowed_proof_types'], true)) {
            dr_respond_json(422, ['success' => false, 'message' => 'Valid proof of address type is required.']);
        }
        if ($proofAddressType !== 'lease' && trim((string)($_POST['proof_address_number'] ?? '')) === '') {
            dr_respond_json(422, ['success' => false, 'message' => 'Proof of address document number is required.']);
        }

        $proofUpload = dr_save_upload(
            $_FILES['proof_address_file'] ?? [],
            'DocumentRequests/' . $generalPermitConfig['folder'] . '/ProofOfAddress',
            ['jpg', 'jpeg', 'png', 'pdf']
        );
        if (!empty($proofUpload['error'])) {
            dr_respond_json(422, ['success' => false, 'message' => 'Proof of address: ' . $proofUpload['error']]);
        }
        $_POST['proof_address_file_path'] = (string)($proofUpload['path'] ?? '');

        $sitePhotoUpload = dr_save_upload(
            $_FILES['site_photo_file'] ?? [],
            'DocumentRequests/' . $generalPermitConfig['folder'] . '/SitePhotos',
            ['jpg', 'jpeg', 'png', 'pdf']
        );
        if (!empty($sitePhotoUpload['error'])) {
            dr_respond_json(422, ['success' => false, 'message' => ($generalPermitConfig['site_photo_label'] ?? 'Picture of establishment / property') . ': ' . $sitePhotoUpload['error']]);
        }
        $_POST['site_photo_file_path'] = (string)($sitePhotoUpload['path'] ?? '');

        $secCertificateError = (int)(($_FILES['sec_certificate_file']['error'] ?? UPLOAD_ERR_NO_FILE));
        if ($needsSecCertificate) {
            $secCertificateUpload = dr_save_upload(
                $_FILES['sec_certificate_file'] ?? [],
                'DocumentRequests/' . $generalPermitConfig['folder'] . '/SECCertificate',
                ['jpg', 'jpeg', 'png', 'pdf']
            );
            if (!empty($secCertificateUpload['error'])) {
                dr_respond_json(422, ['success' => false, 'message' => 'SEC certificate: ' . $secCertificateUpload['error']]);
            }
            $_POST['sec_certificate_file_path'] = (string)($secCertificateUpload['path'] ?? '');
        } elseif ($secCertificateError !== UPLOAD_ERR_NO_FILE) {
            $secCertificateUpload = dr_save_upload(
                $_FILES['sec_certificate_file'] ?? [],
                'DocumentRequests/' . $generalPermitConfig['folder'] . '/SECCertificate',
                ['jpg', 'jpeg', 'png', 'pdf']
            );
            if (!empty($secCertificateUpload['error'])) {
                dr_respond_json(422, ['success' => false, 'message' => 'SEC certificate: ' . $secCertificateUpload['error']]);
            }
            $_POST['sec_certificate_file_path'] = (string)($secCertificateUpload['path'] ?? '');
        } else {
            $_POST['sec_certificate_file_path'] = '';
        }
    }

    $isCohabitationRequest = ($documentTypeToken === 'cohabitation');
    if ($isCohabitationRequest) {
        $cohabitationVariant = trim((string)($_POST['cohabitation_variant'] ?? ''));
        $isRelationshipJailVisit = ($cohabitationVariant === 'relationship_jail_visit' || $cohabitationVariant === 'conjugal_visit');
        if ($isRelationshipJailVisit) {
            $savedRelationshipProofPaths = [];
            $relationshipFiles = $_FILES['relationship_proof_files'] ?? null;
            if (is_array($relationshipFiles) && isset($relationshipFiles['name']) && is_array($relationshipFiles['name'])) {
                $fileCount = min(3, count($relationshipFiles['name']));
                for ($i = 0; $i < $fileCount; $i++) {
                    $entry = [
                        'name' => $relationshipFiles['name'][$i] ?? '',
                        'type' => $relationshipFiles['type'][$i] ?? '',
                        'tmp_name' => $relationshipFiles['tmp_name'][$i] ?? '',
                        'error' => $relationshipFiles['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                        'size' => $relationshipFiles['size'][$i] ?? 0,
                    ];
                    $converted = dr_convert_upload_to_pdf($entry, 'DocumentRequests/RelationshipProofs', $i + 1);
                    if (!empty($converted['error'])) {
                        dr_respond_json(422, ['success' => false, 'message' => 'Proof of relationship attachment ' . ($i + 1) . ': ' . $converted['error']]);
                    }
                    if (!empty($converted['path'])) {
                        $savedRelationshipProofPaths[] = (string)$converted['path'];
                    }
                }
            }
            if (!$savedRelationshipProofPaths) {
                dr_respond_json(422, ['success' => false, 'message' => 'At least one proof of relationship attachment is required.']);
            }
            $_POST['relationship_proof_file_paths'] = $savedRelationshipProofPaths;
            $_POST['relationship_proof_file_path'] = (string)($savedRelationshipProofPaths[0] ?? '');

            $detentionProofType = trim((string)($_POST['detention_proof_type'] ?? ''));
            if ($detentionProofType === '') {
                dr_respond_json(422, ['success' => false, 'message' => 'Proof of detention type is required.']);
            }
            $savedProofPaths = [];
            $detentionFiles = $_FILES['detention_proof_files'] ?? null;
            if (is_array($detentionFiles) && isset($detentionFiles['name']) && is_array($detentionFiles['name'])) {
                $fileCount = min(3, count($detentionFiles['name']));
                for ($i = 0; $i < $fileCount; $i++) {
                    $entry = [
                        'name' => $detentionFiles['name'][$i] ?? '',
                        'type' => $detentionFiles['type'][$i] ?? '',
                        'tmp_name' => $detentionFiles['tmp_name'][$i] ?? '',
                        'error' => $detentionFiles['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                        'size' => $detentionFiles['size'][$i] ?? 0,
                    ];
                    $converted = dr_convert_upload_to_pdf($entry, 'DocumentRequests/DetentionProofs', $i + 1);
                    if (!empty($converted['error'])) {
                        dr_respond_json(422, ['success' => false, 'message' => 'Proof of detention attachment ' . ($i + 1) . ': ' . $converted['error']]);
                    }
                    if (!empty($converted['path'])) {
                        $savedProofPaths[] = (string)$converted['path'];
                    }
                }
            }
            if (!$savedProofPaths) {
                dr_respond_json(422, ['success' => false, 'message' => 'At least one proof of detention attachment is required.']);
            }
            $_POST['detention_proof_file_paths'] = $savedProofPaths;
            $_POST['detention_proof_file_path'] = (string)($savedProofPaths[0] ?? '');
        } else {
            $cohabitantIdType = trim((string)($_POST['cohabitant_id_type'] ?? ''));
            $cohabitantIdNumber = trim((string)($_POST['cohabitant_id_number'] ?? ''));
            if ($cohabitantIdType === '' || $cohabitantIdNumber === '') {
                dr_respond_json(422, ['success' => false, 'message' => 'Partner valid ID type and ID number are required.']);
            }

            $frontUpload = dr_save_upload($_FILES['cohabitant_id_front'] ?? [], 'DocumentRequests/CohabitationPartnerIDs', ['jpg', 'jpeg', 'png', 'webp', 'pdf']);
            if (!empty($frontUpload['error'])) {
                dr_respond_json(422, ['success' => false, 'message' => 'Front of partner valid ID: ' . $frontUpload['error']]);
            }

            $backUploadPath = '';
            if (strcasecmp($cohabitantIdType, 'Passport') !== 0) {
                $backUpload = dr_save_upload($_FILES['cohabitant_id_back'] ?? [], 'DocumentRequests/CohabitationPartnerIDs', ['jpg', 'jpeg', 'png', 'webp', 'pdf']);
                if (!empty($backUpload['error'])) {
                    dr_respond_json(422, ['success' => false, 'message' => 'Back of partner valid ID: ' . $backUpload['error']]);
                }
                $backUploadPath = (string)($backUpload['path'] ?? '');
            }

            $_POST['cohabitant_id_front_path'] = (string)($frontUpload['path'] ?? '');
            $_POST['cohabitant_id_back_path'] = $backUploadPath;
        }
    }

    $payload = $_POST;
    unset($payload['action'], $payload['csrf_token']);

    $now = dr_now();
    $requestId = dr_generate_request_id($conn);
    if (is_array($drSubmitTiming)) {
        $drSubmitTiming['request_id'] = $requestId;
    }

    $pendingStatusId = dr_pick_any_status_id($conn, ['PendingVerification', 'PendingReview', 'Pending']);
    $payloadJson = dr_safe_json($payload);
    $attachmentId = dr_create_request_attachment(
        $conn,
        $residentId,
        $userId,
        $documentType,
        $payloadJson,
        $documentTypeId,
        $pendingStatusId
    );

    $values = [];
    $types = '';
    $params = [];

    $residentNames = dr_get_resident_name_parts($conn, $userId);
    $requestDetails = trim($documentType . ($purpose !== '' ? ' - ' . $purpose : ''));
    if ($requestDetails === '') {
        $requestDetails = 'Document request submitted';
    }
    $requestDetailsToken = dr_request_details_token($documentTypeRaw, $documentType);
    $requestDetailsJsonRequired = dr_request_details_requires_json($conn);
    $requestDetailsValue = $payloadJson;
    $defaultValidity = date('Y-m-d H:i:s', strtotime('+1 year'));

    $setIfColumn = function (string $column, string $type, $value) use (&$values, &$types, &$params, $conn) {
        if (!dr_has_column($conn, 'documentrequesttbl', $column)) {
            return;
        }
        $values[$column] = $value;
        $types .= $type;
        $params[] = $value;
    };

    if (dr_has_column($conn, 'documentrequesttbl', 'request_id') && !dr_request_id_is_numeric($conn)) {
        $setIfColumn('request_id', 's', $requestId);
    }
    $setIfColumn('resident_user_id', 's', $residentForeignId);
    $setIfColumn('resident_id', 's', $residentId);
    $setIfColumn('resident_name', 's', trim($residentNames['firstname'] . ' ' . $residentNames['middlename'] . ' ' . $residentNames['lastname']));
    $setIfColumn('document_type', 's', $documentType);
    if (dr_has_column($conn, 'documentrequesttbl', 'document_type_id') && $documentTypeId) {
        $values['document_type_id'] = (int)$documentTypeId;
        $types .= 'i';
        $params[] = (int)$documentTypeId;
    }
    $setIfColumn('purpose', 's', $purpose);
    $setIfColumn('submitted_at', 's', $now);
    $setIfColumn('created_at', 's', $now);
    $setIfColumn('updated_at', 's', $now);

    // Legacy required columns.
    $setIfColumn('last_name', 's', $residentNames['lastname']);
    $setIfColumn('first_name', 's', $residentNames['firstname']);
    $setIfColumn('middle_name', 's', $residentNames['middlename']);
    $setIfColumn('suffix', 's', $residentNames['suffix']);
    if (dr_has_column($conn, 'documentrequesttbl', 'attachment_id')) {
        if ($attachmentId !== null) {
            $values['attachment_id'] = $attachmentId;
            $types .= 'i';
            $params[] = $attachmentId;
        } else {
            // Short-schema mode allows NULL attachment_id; do not block request creation.
            error_log('[documentRequestWorkflow] attachment not created, continuing with NULL attachment_id');
        }
    }
    $setIfColumn('request_details', 's', $requestDetailsValue);
    if (dr_has_column($conn, 'documentrequesttbl', 'status_id_request') || dr_has_column($conn, 'documentrequesttbl', 'status_id')) {
        if ($pendingStatusId === null) {
            dr_respond_json(500, ['success' => false, 'message' => 'Pending status is not configured.']);
        }
        $statusCol = dr_has_column($conn, 'documentrequesttbl', 'status_id_request') ? 'status_id_request' : 'status_id';
        $values[$statusCol] = $pendingStatusId;
        $types .= 'i';
        $params[] = $pendingStatusId;
    }
    $setIfColumn('request_timestamp', 's', $now);
    $setIfColumn('review_timestamp', 's', null);
    $setIfColumn('release_timestamp', 's', null);
    $setIfColumn('document_validity', 's', $defaultValidity);
    $setIfColumn('qr_code_path', 's', '');

    if (!$values) {
        dr_respond_json(500, ['success' => false, 'message' => 'documentrequesttbl has no compatible columns.']);
    }

    $columns = array_keys($values);
    $placeholders = implode(',', array_fill(0, count($columns), '?'));
    $sql = "INSERT INTO documentrequesttbl (" . implode(',', $columns) . ") VALUES (" . $placeholders . ")";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        dr_respond_json(500, ['success' => false, 'message' => 'Failed to prepare request insert.']);
    }

    $refs = [];
    foreach ($params as $k => $v) {
        $refs[$k] = &$params[$k];
    }
    array_unshift($refs, $types);
    call_user_func_array([$stmt, 'bind_param'], $refs);

    $stmtClosed = false;
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        $stmtClosed = true;

        // Compatibility retry for legacy CHECK constraints on request_details.
        if (stripos($err, 'request_details') !== false && isset($values['request_details'])) {
            if ($requestDetailsJsonRequired) {
                $fallbackCandidates = array_values(array_unique(array_filter([
                    dr_safe_json([
                        'summary' => $requestDetails,
                        'document_type' => $documentType,
                        'purpose' => $purpose,
                    ]),
                    '{}',
                ], static fn($v) => trim((string)$v) !== '')));
            } else {
                $fallbackCandidates = array_values(array_unique(array_filter([
                    $requestDetailsToken,
                    strtolower(trim((string)$documentTypeRaw)),
                    trim((string)$documentType),
                    trim((string)$purpose),
                    $requestDetails,
                    dr_safe_json(['document_type' => $documentType, 'purpose' => $purpose]),
                    '{}',
                    'certificate',
                ], static fn($v) => trim((string)$v) !== '')));
            }

            $saved = false;
            $lastRetryErr = $err;
            foreach ($fallbackCandidates as $fallbackDetails) {
                $values['request_details'] = $fallbackDetails;
                $params = array_values($values);
                $columns = array_keys($values);
                $types = '';
                foreach ($columns as $c) {
                    $types .= ($c === 'attachment_id' || $c === 'status_id' || $c === 'status_id_request' || $c === 'document_type_id') ? 'i' : 's';
                }

                $retrySql = "INSERT INTO documentrequesttbl (" . implode(',', $columns) . ") VALUES (" . implode(',', array_fill(0, count($columns), '?')) . ")";
                $retry = $conn->prepare($retrySql);
                if (!$retry) {
                    continue;
                }

                $retryRefs = [];
                foreach ($params as $k => $v) {
                    $retryRefs[$k] = &$params[$k];
                }
                array_unshift($retryRefs, $types);
                call_user_func_array([$retry, 'bind_param'], $retryRefs);
                if (!$retry->execute()) {
                    $lastRetryErr = $retry->error;
                    $retry->close();
                    continue;
                }

                $requestInsertId = (int)$retry->insert_id;
                if (dr_request_id_is_numeric($conn)) {
                    $requestId = $requestInsertId > 0 ? (string)$requestInsertId : (string)$residentForeignId . '-' . date('YmdHis');
                }
                $saved = true;
                $retry->close();
                break;
            }

            if (!$saved) {
                dr_respond_json(500, ['success' => false, 'message' => 'Failed to save request. ' . $lastRetryErr]);
            }
        } else {
            dr_respond_json(500, ['success' => false, 'message' => 'Failed to save request. ' . $err]);
        }
    } else {
        $requestInsertId = (int)$stmt->insert_id;
        if (dr_request_id_is_numeric($conn)) {
            $requestId = $requestInsertId > 0 ? (string)$requestInsertId : (string)$residentForeignId . '-' . date('YmdHis');
        }
    }
    if (!$stmtClosed) {
        $stmt->close();
    }

    $row = dr_fetch_request($conn, $requestId);
    if ($row) {
        dr_sync_transaction($conn, $row);
    }

    if (dr_wants_html_redirect()) {
        header('Location: ' . appUrl('/Resident-End/document_requests.php?created=' . urlencode($requestId)));
        exit;
    }

    dr_respond_json(200, [
        'success' => true,
        'message' => 'Document request submitted.',
        'request_id' => $requestId,
    ]);
}

if ($action === 'submit_payment') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        dr_respond_json(405, ['success' => false, 'message' => 'Method not allowed.']);
    }

    $requestId = trim((string)($_POST['request_id'] ?? ''));
    if ($requestId === '') {
        dr_respond_json(422, ['success' => false, 'message' => 'Missing request ID.']);
    }

    $stageCol = dr_has_column($conn, 'documentrequesttbl', 'stage') ? 'stage' : null;
    $statusCol = dr_request_status_column($conn);
    $selCols = [
        'request_id',
        'resident_user_id',
        'document_type',
    ];
    if ($stageCol !== null) {
        $selCols[] = 'stage';
    }
    if ($statusCol !== null && !in_array($statusCol, $selCols, true)) {
        $selCols[] = $statusCol;
    }
    $selSql = "SELECT " . implode(', ', $selCols) . " FROM documentrequesttbl WHERE request_id = ? LIMIT 1";
    $sel = $conn->prepare($selSql);
    if (!$sel) {
        dr_respond_json(500, ['success' => false, 'message' => 'Failed to prepare request lookup.']);
    }
    $sel->bind_param('s', $requestId);
    $sel->execute();
    $row = $sel->get_result()->fetch_assoc();
    $sel->close();
    if (!$row || (string)($row['resident_user_id'] ?? '') !== $residentForeignId) {
        dr_respond_json(404, ['success' => false, 'message' => 'Request not found.']);
    }

    if ($stageCol === null && $statusCol !== null) {
        $statusId = (int)($row[$statusCol] ?? 0);
        if ($statusId > 0) {
            $statusName = dr_status_name_by_id($conn, $statusId);
            $mapped = dr_status_name_to_stage($statusName);
            if ($mapped !== null) {
                $row['stage'] = $mapped;
            }
        }
    }

    $stage = trim((string)($row['stage'] ?? ''));
    if (!in_array($stage, [DR_STAGE_FOR_PAYMENT, DR_STAGE_PAYMENT_REJECTED], true)) {
        dr_respond_json(422, ['success' => false, 'message' => 'Request is not ready for payment submission.']);
    }

    $paymentMethod = strtolower(trim((string)($_POST['payment_method'] ?? 'gcash')));
    if (!in_array($paymentMethod, ['gcash', 'barangay'], true)) {
        $paymentMethod = 'gcash';
    }
    if ($paymentMethod !== 'gcash') {
        dr_respond_json(422, ['success' => false, 'message' => 'Online submission is only for GCash payments. For barangay payment, pay at the finance window.']);
    }

    $proofPath = null;
    $paymentReference = null;
    if ($paymentMethod === 'gcash') {
        $paymentReference = trim((string)($_POST['payment_reference'] ?? ''));
        if ($paymentReference === '') {
            dr_respond_json(422, ['success' => false, 'message' => 'GCash transaction number is required.']);
        }
        $upload = dr_save_upload($_FILES['payment_proof'] ?? [], 'DocumentPayments', ['jpg', 'jpeg', 'png', 'webp']);
        $proofPath = (string)($upload['path'] ?? '');
        if ($proofPath === '') {
            $msg = trim((string)($upload['error'] ?? ''));
            dr_respond_json(422, ['success' => false, 'message' => $msg !== '' ? $msg : 'GCash payment proof is required.']);
        }
    }

    $now = dr_now();
    $requestStatusId = dr_find_request_status_id_by_stage($conn, DR_STAGE_PAYMENT_SUBMITTED);
    $txStatusId = dr_map_stage_to_transaction_status_id($conn, DR_STAGE_PAYMENT_SUBMITTED);

    $docSets = [];
    $docTypes = '';
    $docVals = [];
    if (dr_has_column($conn, 'documentrequesttbl', 'updated_at')) {
        $docSets[] = 'updated_at = ?';
        $docTypes .= 's';
        $docVals[] = $now;
    }
    if (dr_has_column($conn, 'documentrequesttbl', 'stage')) {
        $docSets[] = 'stage = ?';
        $docTypes .= 's';
        $docVals[] = DR_STAGE_PAYMENT_SUBMITTED;
    }
    $statusCol = dr_request_status_column($conn);
    if ($statusCol !== null && $requestStatusId !== null) {
        $docSets[] = $statusCol . ' = ?';
        $docTypes .= 'i';
        $docVals[] = $requestStatusId;
    }
    if (dr_has_column($conn, 'documentrequesttbl', 'review_timestamp')) {
        $docSets[] = 'review_timestamp = ?';
        $docTypes .= 's';
        $docVals[] = $now;
    }
    if (dr_has_column($conn, 'documentrequesttbl', 'status_remarks')) {
        $docSets[] = 'status_remarks = NULL';
    }
    if (!$docSets) {
        dr_respond_json(500, ['success' => false, 'message' => 'Unable to update payment state.']);
    }
    $docTypes .= 's';
    $docVals[] = $requestId;
    $docSql = 'UPDATE documentrequesttbl SET ' . implode(', ', $docSets) . ' WHERE request_id = ? LIMIT 1';
    $docStmt = $conn->prepare($docSql);
    if (!$docStmt) {
        dr_respond_json(500, ['success' => false, 'message' => 'Unable to prepare payment update.']);
    }
    $docRefs = [];
    foreach ($docVals as $i => $v) {
        $docRefs[$i] = &$docVals[$i];
    }
    array_unshift($docRefs, $docTypes);
    call_user_func_array([$docStmt, 'bind_param'], $docRefs);
    $docOk = $docStmt->execute();
    $docStmt->close();
    if (!$docOk) {
        dr_respond_json(500, ['success' => false, 'message' => 'Unable to update payment state.']);
    }

    // Fast finance transaction write using upsert-style insert.
    if (dr_table_exists($conn, 'financetransactiontbl')) {
        $txDetails = dr_safe_json([
            'reference' => $paymentReference,
            'submitted_at' => $now,
        ]);
        $hasProofCol = dr_column_exists($conn, 'financetransactiontbl', 'payment_proof_path');
        $hasDecisionCol = dr_column_exists($conn, 'financetransactiontbl', 'finance_decision_at');
        $hasPaymentTs = dr_column_exists($conn, 'financetransactiontbl', 'payment_timestamp');
        $hasPaymentMethod = dr_column_exists($conn, 'financetransactiontbl', 'payment_method');
        $hasTxDetails = dr_column_exists($conn, 'financetransactiontbl', 'transaction_details');
        $hasTxStatus = dr_column_exists($conn, 'financetransactiontbl', 'transaction_status_id');
        $hasOrNumber = dr_column_exists($conn, 'financetransactiontbl', 'or_number');

        $txId = '';
        $existingTx = $conn->prepare("SELECT transaction_id FROM financetransactiontbl WHERE request_id = ? LIMIT 1");
        if ($existingTx) {
            $existingTx->bind_param('s', $requestId);
            $existingTx->execute();
            $existingRow = $existingTx->get_result()->fetch_assoc();
            $existingTx->close();
            if (is_array($existingRow)) {
                $txId = trim((string)($existingRow['transaction_id'] ?? ''));
            }
        }
        if ($txId === '') {
            if (function_exists('GenerateTransactionID')) {
                $txId = (string)GenerateTransactionID($conn, 'financetransactiontbl', 'transaction_id');
            }
            if ($txId === '') {
                $txId = strtoupper(substr(bin2hex(random_bytes(8)), 0, 10));
            }
        }

        $insertCols = ['transaction_id', 'request_id'];
        $insertVals = [$txId, $requestId];
        $insertTypes = 'ss';
        $updateSets = [];

        if ($hasPaymentMethod) {
            $insertCols[] = 'payment_method';
            $insertVals[] = $paymentMethod;
            $insertTypes .= 's';
            $updateSets[] = 'payment_method = VALUES(payment_method)';
        }
        if ($hasProofCol) {
            $insertCols[] = 'payment_proof_path';
            $insertVals[] = $proofPath;
            $insertTypes .= 's';
            $updateSets[] = 'payment_proof_path = VALUES(payment_proof_path)';
        }
        if ($hasTxDetails) {
            $insertCols[] = 'transaction_details';
            $insertVals[] = $txDetails;
            $insertTypes .= 's';
            $updateSets[] = 'transaction_details = VALUES(transaction_details)';
        }
        if ($hasTxStatus) {
            $insertCols[] = 'transaction_status_id';
            $insertVals[] = $txStatusId;
            $insertTypes .= 'i';
            $updateSets[] = 'transaction_status_id = VALUES(transaction_status_id)';
        }
        if ($hasPaymentTs) {
            $insertCols[] = 'payment_timestamp';
            $insertVals[] = $now;
            $insertTypes .= 's';
            $updateSets[] = 'payment_timestamp = VALUES(payment_timestamp)';
        }
        if ($hasOrNumber) {
            $updateSets[] = 'or_number = NULL';
        }
        if ($hasDecisionCol) {
            $updateSets[] = 'finance_decision_at = NULL';
        }
        if (dr_column_exists($conn, 'financetransactiontbl', 'updated_at')) {
            $updateSets[] = 'updated_at = CURRENT_TIMESTAMP';
        }

        if (!$updateSets) {
            $updateSets[] = 'request_id = request_id';
        }
        $insSql = "INSERT INTO financetransactiontbl (" . implode(', ', $insertCols) . ")
                   VALUES (" . implode(', ', array_fill(0, count($insertCols), '?')) . ")
                   ON DUPLICATE KEY UPDATE " . implode(', ', $updateSets);
        $ins = $conn->prepare($insSql);
        $financeSaved = false;
        if ($ins) {
            $insRefs = [];
            foreach ($insertVals as $i => $v) {
                $insRefs[$i] = &$insertVals[$i];
            }
            array_unshift($insRefs, $insertTypes);
            call_user_func_array([$ins, 'bind_param'], $insRefs);
            $financeSaved = (bool)$ins->execute();
            $ins->close();
        }

        if (!$financeSaved) {
            dr_respond_json(500, ['success' => false, 'message' => 'Payment was uploaded but finance queue update failed. Please submit again.']);
        }
    }

    dr_respond_json(200, [
        'success' => true,
        'message' => 'Payment submitted. Please wait for finance verification.',
        'request_id' => $requestId,
        'stage' => DR_STAGE_PAYMENT_SUBMITTED,
        'payment_method' => $paymentMethod,
        'payment_submitted_at' => $now,
    ]);
}

if ($action === 'select_payment_mode') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        dr_respond_json(405, ['success' => false, 'message' => 'Method not allowed.']);
    }

    $requestId = trim((string)($_POST['request_id'] ?? ''));
    if ($requestId === '') {
        dr_respond_json(422, ['success' => false, 'message' => 'Missing request ID.']);
    }

    $stageCol = dr_has_column($conn, 'documentrequesttbl', 'stage') ? 'stage' : null;
    $statusCol = dr_request_status_column($conn);
    $selCols = [
        'request_id',
        'resident_user_id',
    ];
    if ($stageCol !== null) {
        $selCols[] = 'stage';
    }
    if ($statusCol !== null && !in_array($statusCol, $selCols, true)) {
        $selCols[] = $statusCol;
    }
    $selSql = "SELECT " . implode(', ', $selCols) . " FROM documentrequesttbl WHERE request_id = ? LIMIT 1";
    $sel = $conn->prepare($selSql);
    if (!$sel) {
        dr_respond_json(500, ['success' => false, 'message' => 'Failed to prepare request lookup.']);
    }
    $sel->bind_param('s', $requestId);
    $sel->execute();
    $row = $sel->get_result()->fetch_assoc();
    $sel->close();
    if (!$row || (string)($row['resident_user_id'] ?? '') !== $residentForeignId) {
        dr_respond_json(404, ['success' => false, 'message' => 'Request not found.']);
    }

    if ($stageCol === null && $statusCol !== null) {
        $statusId = (int)($row[$statusCol] ?? 0);
        if ($statusId > 0) {
            $statusName = dr_status_name_by_id($conn, $statusId);
            $mapped = dr_status_name_to_stage($statusName);
            if ($mapped !== null) {
                $row['stage'] = $mapped;
            }
        }
    }

    $stage = trim((string)($row['stage'] ?? ''));
    if (!in_array($stage, [DR_STAGE_FOR_PAYMENT, DR_STAGE_PAYMENT_REJECTED], true)) {
        dr_respond_json(422, ['success' => false, 'message' => 'Payment mode can only be selected while request is for payment.']);
    }

    $paymentMethod = strtolower(trim((string)($_POST['payment_method'] ?? '')));
    if (!in_array($paymentMethod, ['gcash', 'barangay'], true)) {
        dr_respond_json(422, ['success' => false, 'message' => 'Please choose a valid payment method.']);
    }

    // Fast-path update: avoid expensive full workflow recalculation for simple mode selection.
    if ($stage === DR_STAGE_PAYMENT_REJECTED) {
        $updated = dr_update_stage($conn, $requestId, DR_STAGE_FOR_PAYMENT, [
            'payment_method' => $paymentMethod,
            'payment_proof_path' => null,
            'payment_submitted_at' => null,
            'payment_reference' => null,
            'status_remarks' => null,
        ]);
        if (!$updated) {
            dr_respond_json(500, ['success' => false, 'message' => 'Unable to save payment mode.']);
        }
    } else {
        if (dr_table_exists($conn, 'financetransactiontbl')) {
            $proofCol = dr_column_exists($conn, 'financetransactiontbl', 'payment_proof_path');
            $decisionCol = dr_column_exists($conn, 'financetransactiontbl', 'finance_decision_at');
            $detailsCol = dr_column_exists($conn, 'financetransactiontbl', 'transaction_details');
            $sql = "UPDATE financetransactiontbl
                    SET payment_method = ?, payment_timestamp = NULL, or_number = NULL, updated_at = CURRENT_TIMESTAMP";
            if ($proofCol) {
                $sql .= ", payment_proof_path = NULL";
            }
            if ($decisionCol) {
                $sql .= ", finance_decision_at = NULL";
            }
            if ($detailsCol) {
                $sql .= ", transaction_details = NULL";
            }
            $sql .= " WHERE request_id = ? LIMIT 1";
            $upd = $conn->prepare($sql);
            if ($upd) {
                $upd->bind_param('ss', $paymentMethod, $requestId);
                $upd->execute();
                $upd->close();
            }
        }
        if (dr_has_column($conn, 'documentrequesttbl', 'status_remarks')) {
            $clearRemarks = $conn->prepare("UPDATE documentrequesttbl SET status_remarks = NULL WHERE request_id = ? LIMIT 1");
            if ($clearRemarks) {
                $clearRemarks->bind_param('s', $requestId);
                $clearRemarks->execute();
                $clearRemarks->close();
            }
        }
    }

    dr_respond_json(200, [
        'success' => true,
        'message' => 'Payment mode saved.',
        'request_id' => $requestId,
        'payment_method' => $paymentMethod,
    ]);
}

if ($action === 'download_issued') {
    $requestId = trim((string)($_GET['request_id'] ?? ''));
    if ($requestId === '') {
        http_response_code(422);
        exit('Missing request ID.');
    }

    $row = dr_fetch_request($conn, $requestId);
    if (!$row || (string)$row['resident_user_id'] !== $residentForeignId) {
        http_response_code(404);
        exit('Request not found.');
    }

    $stage = (string)($row['stage'] ?? '');
    if (!in_array($stage, [DR_STAGE_COMPLETED], true)) {
        http_response_code(422);
        exit('Document is not yet available for download. Release status is pending final review.');
    }

    $publicPath = trim((string)($row['issued_file_path'] ?? ''));
    if ($publicPath === '') {
        http_response_code(404);
        exit('Issued file is not yet uploaded.');
    }

    $baseDir = realpath(__DIR__ . '/../../');
    if ($baseDir === false) {
        http_response_code(500);
        exit('Path resolution failed.');
    }

    $relative = '/' . ltrim(dr_strip_legacy_base($publicPath), '/');
    $absolute = realpath($baseDir . $relative);

    if ($absolute === false || !is_file($absolute) || strpos($absolute, $baseDir . '/UnifiedFileAttachment/') !== 0) {
        http_response_code(404);
        exit('File not found.');
    }

    $filename = basename($absolute);
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($absolute));
    readfile($absolute);
    exit;
}

if ($action === 'view_issued') {
    $requestId = trim((string)($_GET['request_id'] ?? ''));
    if ($requestId === '') {
        http_response_code(422);
        exit('Missing request ID.');
    }

    $row = dr_fetch_request($conn, $requestId);
    if (!$row || (string)$row['resident_user_id'] !== $residentForeignId) {
        http_response_code(404);
        exit('Request not found.');
    }

    $stage = (string)($row['stage'] ?? '');
    if (!in_array($stage, [DR_STAGE_COMPLETED], true)) {
        http_response_code(422);
        exit('Document is not yet available for viewing.');
    }

    $publicPath = trim((string)($row['issued_file_path'] ?? ''));
    if ($publicPath === '') {
        http_response_code(404);
        exit('Issued file is not yet uploaded.');
    }

    $baseDir = realpath(__DIR__ . '/../../');
    if ($baseDir === false) {
        http_response_code(500);
        exit('Path resolution failed.');
    }

    $relative = '/' . ltrim(dr_strip_legacy_base($publicPath), '/');
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

if ($action === 'download_invoice') {
    $requestId = trim((string)($_GET['request_id'] ?? ''));
    if ($requestId === '') {
        http_response_code(422);
        exit('Missing request ID.');
    }

    $row = dr_fetch_request($conn, $requestId);
    if (!$row || (string)$row['resident_user_id'] !== $residentForeignId) {
        http_response_code(404);
        exit('Request not found.');
    }

    $baseDir = realpath(__DIR__ . '/../../');
    if ($baseDir === false) {
        http_response_code(500);
        exit('Path resolution failed.');
    }

    $resolvedAmount = is_numeric((string)($row['amount'] ?? null))
        ? (float)$row['amount']
        : (is_numeric((string)($row['fee_amount'] ?? null)) ? (float)$row['fee_amount'] : 0.0);
    $resolvedOrNumber = trim((string)($row['or_number'] ?? ''));
    $invoicePublicPath = trim((string)($row['invoice_file_path'] ?? ''));

    if ($resolvedAmount > 0.0 && $resolvedOrNumber !== '') {
        require_once $baseDir . '/PhpFiles/General/invoiceGenerator.php';
        $feeBreakdown = [];
        if (dr_is_clearance_document_type((string)($row['document_type'] ?? ''))) {
            foreach (dr_get_clearance_fees_for_request($conn, $requestId) as $feeRow) {
                $label = trim((string)($feeRow['fee_type'] ?? $feeRow['fee_name'] ?? ''));
                $feeAmount = (float)($feeRow['amount'] ?? 0);
                if ($label === '' || $feeAmount < 0) {
                    continue;
                }
                $feeBreakdown[] = [
                    'label' => $label,
                    'amount' => $feeAmount,
                ];
            }
        }
        $regeneratedPath = dr_generate_invoice_pdf(array_merge($row, [
            'amount' => $resolvedAmount,
            'or_number' => $resolvedOrNumber,
            'fee_breakdown' => $feeBreakdown,
        ]), $baseDir);
        if (is_string($regeneratedPath) && trim($regeneratedPath) !== '') {
            $invoicePublicPath = trim($regeneratedPath);
            $safeRelPath = $conn->real_escape_string($invoicePublicPath);
            $safeReqId = $conn->real_escape_string($requestId);
            $conn->query("UPDATE documentrequesttbl SET invoice_file_path = '{$safeRelPath}' WHERE request_id = '{$safeReqId}'");
        }
    }

    if ($invoicePublicPath === '') {
        http_response_code(404);
        exit('Invoice not available for this request.');
    }

    $relative = '/' . ltrim(dr_strip_legacy_base($invoicePublicPath), '/');
    $absolute = realpath($baseDir . $relative);

    if ($absolute === false || !is_file($absolute) || strpos($absolute, $baseDir . '/UnifiedFileAttachment/') !== 0) {
        http_response_code(404);
        exit('Invoice file not found.');
    }

    $disposition = strtolower(trim((string)($_GET['disposition'] ?? 'attachment')));
    $contentDisposition = in_array($disposition, ['inline', 'view', 'open'], true)
        ? 'inline'
        : 'attachment';

    $safeId   = preg_replace('/[^A-Za-z0-9_-]/', '', $requestId);
    $filename = 'Official_Receipt_' . $safeId . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . $contentDisposition . '; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($absolute));
    readfile($absolute);
    exit;
}

if ($action === 'view_payment_proof') {
    $requestId = trim((string)($_GET['request_id'] ?? ''));
    if ($requestId === '') {
        http_response_code(422);
        exit('Missing request ID.');
    }

    $row = dr_fetch_request($conn, $requestId);
    if (!$row || (string)$row['resident_user_id'] !== $residentForeignId) {
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

    $relative = '/' . ltrim(dr_strip_legacy_base($publicPath), '/');
    $absolute = realpath($baseDir . $relative);
    if ($absolute === false || !is_file($absolute) || strpos($absolute, $baseDir . '/UnifiedFileAttachment/') !== 0) {
        http_response_code(404);
        exit('File not found.');
    }

    $mime = (string)(mime_content_type($absolute) ?: 'application/octet-stream');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . basename($absolute) . '"');
    header('Content-Length: ' . filesize($absolute));
    readfile($absolute);
    exit;
}

if ($action === 'list') {
    if (!dr_table_exists($conn, 'documentrequesttbl')) {
        dr_respond_json(200, ['success' => true, 'items' => []]);
    }

    $orderCol = dr_has_column($conn, 'documentrequesttbl', 'submitted_at') ? 'submitted_at' : 'request_timestamp';
    $hasFinanceTable = dr_table_exists($conn, 'financetransactiontbl');
    $hasStatusLookup = dr_table_exists($conn, 'statuslookuptbl');
    $limit = max(1, min(300, (int)($_GET['limit'] ?? 120)));
    if ($hasFinanceTable) {
        $sql = "
            SELECT
                d.*,
                f.transaction_amount AS _tx_amount,
                f.payment_method AS _tx_payment_method,
                f.payment_proof_path AS _tx_payment_proof_path,
                f.transaction_details AS _tx_transaction_details,
                f.or_number AS _tx_or_number,
                f.transaction_status_id AS _tx_status_id,
                " . ($hasStatusLookup ? "s.status_name" : "''") . " AS _tx_status_name,
                f.payment_deadline AS _tx_payment_deadline,
                f.payment_timestamp AS _tx_payment_timestamp,
                f.finance_decision_at AS _tx_finance_decision_at,
                f.user_id_employee_process AS _tx_finance_user_id
            FROM documentrequesttbl d
            LEFT JOIN financetransactiontbl f ON f.request_id = d.request_id
            " . ($hasStatusLookup ? "LEFT JOIN statuslookuptbl s ON s.status_id = f.transaction_status_id" : "") . "
            WHERE d.resident_user_id = ?
            ORDER BY d.{$orderCol} DESC, d.request_id DESC
            LIMIT {$limit}
        ";
    } else {
        $sql = "
            SELECT
                d.*,
                NULL AS _tx_amount,
                '' AS _tx_payment_method,
                '' AS _tx_payment_proof_path,
                '' AS _tx_transaction_details,
                '' AS _tx_or_number,
                0 AS _tx_status_id,
                '' AS _tx_status_name,
                '' AS _tx_payment_deadline,
                '' AS _tx_payment_timestamp,
                '' AS _tx_finance_decision_at,
                '' AS _tx_finance_user_id
            FROM documentrequesttbl d
            WHERE d.resident_user_id = ?
            ORDER BY d.{$orderCol} DESC, d.request_id DESC
            LIMIT {$limit}
        ";
    }
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        dr_respond_json(500, ['success' => false, 'message' => 'Failed to prepare list query.']);
    }
    $stmt->bind_param('s', $residentForeignId);
    $stmt->execute();
    $items = [];
    $docTypesForFee = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        dr_hydrate_request_derived_fields($conn, $row, false);

        // Populate finance data from join (avoid per-row finance query).
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
        if ($txDetails !== '') {
            $decoded = json_decode($txDetails, true);
            if (is_array($decoded)) {
                $ref = trim((string)($decoded['reference'] ?? ''));
                if ($ref !== '') {
                    $row['payment_reference'] = $ref;
                }
            } elseif (preg_match('/\bReference:\s*(.+)$/mi', $txDetails, $m)) {
                $row['payment_reference'] = trim((string)($m[1] ?? ''));
            }
        }

        if (trim((string)($row['stage'] ?? '')) === '') {
            dr_sync_stage_from_status_lookup($conn, $row);
        }
        $row['stage_label'] = dr_stage_label((string)$row['stage']);
        $docTypeForFee = trim((string)($row['document_type'] ?? ''));
        $storedFeeAmount = $row['fee_amount'] ?? null;
        if (strcasecmp($docTypeForFee, 'Barangay ID') === 0) {
            $row['fee_amount'] = 0.0;
        } elseif ($storedFeeAmount !== null && $storedFeeAmount !== '' && is_numeric((string)$storedFeeAmount)) {
            $row['fee_amount'] = (float)$storedFeeAmount;
        } elseif (isset($row['_tx_amount']) && $row['_tx_amount'] !== null && $row['_tx_amount'] !== '' && is_numeric((string)$row['_tx_amount'])) {
            $row['fee_amount'] = (float)$row['_tx_amount'];
        } else {
            $row['fee_amount'] = null;
            if ($docTypeForFee !== '' && strcasecmp($docTypeForFee, 'Barangay ID') !== 0) {
                $docTypesForFee[$docTypeForFee] = true;
            }
        }
        $payload = json_decode((string)($row['request_details'] ?? $row['payload_json'] ?? '{}'), true);
        $row['payload'] = is_array($payload) ? $payload : [];
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
            $row['_tx_finance_user_id']
        );
        $items[] = $row;
    }
    $stmt->close();

    if ($items && $docTypesForFee) {
        $feeMap = dr_get_fee_map_for_document_types($conn, array_keys($docTypesForFee));
        foreach ($items as &$it) {
            $docType = trim((string)($it['document_type'] ?? ''));
            if ($it['fee_amount'] === null && $docType !== '' && array_key_exists($docType, $feeMap)) {
                $it['fee_amount'] = $feeMap[$docType];
            }
            $baseFee = (isset($it['fee_amount']) && $it['fee_amount'] !== null && $it['fee_amount'] !== '' && is_numeric((string)$it['fee_amount']))
                ? (float)$it['fee_amount']
                : null;
            $it['fee_amount'] = dr_get_effective_document_fee_amount($conn, $docType, $it, $baseFee);
        }
        unset($it);
    } elseif ($items) {
        foreach ($items as &$it) {
            $docType = trim((string)($it['document_type'] ?? ''));
            $baseFee = (isset($it['fee_amount']) && $it['fee_amount'] !== null && $it['fee_amount'] !== '' && is_numeric((string)$it['fee_amount']))
                ? (float)$it['fee_amount']
                : null;
            $it['fee_amount'] = dr_get_effective_document_fee_amount($conn, $docType, $it, $baseFee);
        }
        unset($it);
    }

    dr_respond_json(200, ['success' => true, 'items' => $items]);
}

if ($action === 'get_clearance_fees') {
    $requestId = trim((string)($_GET['request_id'] ?? $_POST['request_id'] ?? ''));
    if ($requestId === '') {
        dr_respond_json(400, ['success' => false, 'message' => 'Missing request_id.']);
    }
    $fees = dr_get_clearance_fees_for_request($conn, $requestId);
    $total = array_sum(array_column($fees, 'amount'));
    dr_respond_json(200, ['success' => true, 'fees' => $fees, 'total' => $total]);
}

dr_respond_json(404, ['success' => false, 'message' => 'Unknown action.']);
