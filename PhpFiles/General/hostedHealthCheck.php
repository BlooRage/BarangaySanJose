<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (trim((string)($_SESSION['user_id'] ?? '')) === '') {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Login required.',
    ]);
    exit;
}

require_once __DIR__ . '/runtimeConfig.php';
require_once __DIR__ . '/mailConfigurations.php';
require_once __DIR__ . '/sendSMS.php';

$projectRoot = runtime_project_root();

$autoloadCandidates = [
    $projectRoot . '/composer-email-handler/vendor/autoload.php',
    $projectRoot . '/vendor/autoload.php',
];
$autoloadLoaded = false;
foreach ($autoloadCandidates as $autoloadCandidate) {
    if (is_file($autoloadCandidate)) {
        require_once $autoloadCandidate;
        $autoloadLoaded = true;
    }
}

$pdfMergeSupportPath = $projectRoot . '/PhpFiles/Resident-End/pdfMergeSupport.php';
if (is_file($pdfMergeSupportPath)) {
    require_once $pdfMergeSupportPath;
}

function hosted_health_relpath(string $projectRoot, string $path): string
{
    $normalizedRoot = rtrim(str_replace('\\', '/', $projectRoot), '/');
    $normalizedPath = str_replace('\\', '/', $path);
    if ($normalizedRoot !== '' && str_starts_with($normalizedPath, $normalizedRoot . '/')) {
        return substr($normalizedPath, strlen($normalizedRoot) + 1);
    }
    return $normalizedPath;
}

function hosted_health_temp_probe(): array
{
    $dir = sys_get_temp_dir();
    $result = [
        'dir' => $dir,
        'dir_exists' => is_dir($dir),
        'dir_writable' => is_dir($dir) && is_writable($dir),
        'probe_ok' => false,
        'error' => '',
    ];

    if (!$result['dir_exists'] || !$result['dir_writable']) {
        $result['error'] = 'Temporary directory is missing or not writable.';
        return $result;
    }

    $probe = @tempnam($dir, 'health_');
    if ($probe === false) {
        $result['error'] = 'Unable to create a temp file.';
        return $result;
    }

    $writeOk = @file_put_contents($probe, 'ok');
    $result['probe_ok'] = $writeOk !== false;
    if (!$result['probe_ok']) {
        $result['error'] = 'Temp file write failed.';
    }
    @unlink($probe);

    return $result;
}

function hosted_health_document_request_pdf_smoke(): array
{
    if (!class_exists(\setasign\Fpdi\Fpdi::class)) {
        return ['ok' => false, 'error' => 'FPDI is unavailable.'];
    }
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagejpeg')) {
        return ['ok' => false, 'error' => 'GD JPEG support is unavailable.'];
    }

    $tmpImage = @tempnam(sys_get_temp_dir(), 'img');
    if ($tmpImage === false) {
        return ['ok' => false, 'error' => 'Unable to allocate temp image file.'];
    }

    $tmpPdf = sys_get_temp_dir() . '/hosted-health-' . bin2hex(random_bytes(4)) . '.pdf';
    try {
        $img = imagecreatetruecolor(160, 120);
        if ($img === false) {
            return ['ok' => false, 'error' => 'Unable to create in-memory test image.'];
        }

        $white = imagecolorallocate($img, 255, 255, 255);
        $green = imagecolorallocate($img, 30, 150, 90);
        imagefill($img, 0, 0, $white);
        imagefilledrectangle($img, 15, 15, 145, 105, $green);
        imagejpeg($img, $tmpImage, 90);
        imagedestroy($img);

        $imageInfo = @getimagesize($tmpImage);
        if ($imageInfo === false || !isset($imageInfo[0], $imageInfo[1])) {
            return ['ok' => false, 'error' => 'Generated test image could not be read back.'];
        }

        $imgW = (float)$imageInfo[0];
        $imgH = (float)$imageInfo[1];
        $orientation = $imgW > $imgH ? 'L' : 'P';

        $pdf = new \setasign\Fpdi\Fpdi();
        $pdf->AddPage($orientation, 'A4');

        $margin = 10.0;
        $pageW = (float)$pdf->GetPageWidth();
        $pageH = (float)$pdf->GetPageHeight();
        $scale = min(($pageW - ($margin * 2)) / $imgW, ($pageH - ($margin * 2)) / $imgH);
        $drawW = $imgW * $scale;
        $drawH = $imgH * $scale;
        $x = ($pageW - $drawW) / 2;
        $y = ($pageH - $drawH) / 2;

        // This mimics the hosted upload case where the temp file has no extension.
        $pdf->Image($tmpImage, $x, $y, $drawW, $drawH, 'jpeg');
        $pdf->Output('F', $tmpPdf);

        if (!is_file($tmpPdf) || filesize($tmpPdf) <= 0) {
            return ['ok' => false, 'error' => 'PDF output was not created.'];
        }

        return ['ok' => true, 'bytes' => filesize($tmpPdf)];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => trim((string)$e->getMessage()) ?: 'Document-request PDF smoke test failed.'];
    } finally {
        @unlink($tmpImage);
        @unlink($tmpPdf);
    }
}

$mailConfig = require __DIR__ . '/mailConfigurations.php';
$runtimeSourceRows = [];
foreach (runtime_config_sources() as $path) {
    $runtimeSourceRows[] = [
        'path' => hosted_health_relpath($projectRoot, $path),
        'present' => is_file($path),
        'readable' => is_readable($path),
    ];
}

$tempProbe = hosted_health_temp_probe();
$pdfSmoke = hosted_health_document_request_pdf_smoke();

echo json_encode([
    'success' => true,
    'checked_at' => date('Y-m-d H:i:s'),
    'runtime' => [
        'sources' => $runtimeSourceRows,
        'has_real_runtime_config' => array_reduce($runtimeSourceRows, static function (bool $carry, array $row): bool {
            return $carry || (!str_contains($row['path'], 'example') && !empty($row['present']));
        }, false),
    ],
    'mail' => [
        'host_set' => trim((string)($mailConfig['host'] ?? '')) !== '',
        'username_set' => trim((string)($mailConfig['username'] ?? '')) !== '',
        'password_set' => (string)($mailConfig['password'] ?? '') !== '',
        'from_email_set' => trim((string)($mailConfig['from_email'] ?? '')) !== '',
        'smtp_auth' => (bool)($mailConfig['smtp_auth'] ?? false),
        'secure' => trim((string)($mailConfig['secure'] ?? '')),
        'port' => (int)($mailConfig['port'] ?? 0),
    ],
    'sms' => [
        'api_key_set' => trim((string)runtime_config('sms.semaphore_api_key', '')) !== '',
        'sender_set' => trim((string)runtime_config('sms.sender', '')) !== '',
        'endpoint' => trim((string)runtime_config('sms.endpoint', '')),
        'otp_endpoint' => trim((string)runtime_config('sms.otp_endpoint', '')),
        'last_error' => getLastSmsError(),
    ],
    'extensions' => [
        'curl' => function_exists('curl_init'),
        'gd' => extension_loaded('gd'),
        'openssl' => extension_loaded('openssl'),
        'allow_url_fopen' => runtime_bool(ini_get('allow_url_fopen'), false),
        'exif_imagetype' => function_exists('exif_imagetype'),
        'imagecreatefromwebp' => function_exists('imagecreatefromwebp'),
        'imagepng' => function_exists('imagepng'),
    ],
    'pdf' => [
        'autoload_loaded' => $autoloadLoaded,
        'fpdi_available' => class_exists(\setasign\Fpdi\Fpdi::class),
        'resident_pdf_helper_present' => is_file($pdfMergeSupportPath),
        'temp_probe' => $tempProbe,
        'document_request_temp_image_pdf' => $pdfSmoke,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
