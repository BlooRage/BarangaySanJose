<?php
declare(strict_types=1);

require_once __DIR__ . '/uploadLimits.php';

if (!function_exists('fps_default_payment_settings')) {
    function fps_default_payment_settings(): array
    {
        return [
            'online_payment_enabled' => false,
            'online_payment_label' => 'GCash',
            'online_payment_qr_path' => '/Images/GCASH_QR.jpg',
            'updated_by_user_id' => '',
            'updated_at' => '',
        ];
    }
}

if (!function_exists('fps_ensure_payment_settings_table')) {
    function fps_ensure_payment_settings_table(mysqli $conn): void
    {
        static $done = false;
        if ($done) {
            return;
        }

        $conn->query("
            CREATE TABLE IF NOT EXISTS financepaymentsettingstbl (
                setting_id TINYINT UNSIGNED NOT NULL DEFAULT 1,
                online_payment_enabled TINYINT(1) NOT NULL DEFAULT 0,
                online_payment_label VARCHAR(60) NOT NULL DEFAULT 'GCash',
                online_payment_qr_path VARCHAR(500) NOT NULL DEFAULT '/Images/GCASH_QR.jpg',
                updated_by_user_id VARCHAR(64) DEFAULT NULL,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (setting_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $done = true;
    }
}

if (!function_exists('fps_resolve_payment_settings')) {
    function fps_resolve_payment_settings(mysqli $conn): array
    {
        fps_ensure_payment_settings_table($conn);
        $defaults = fps_default_payment_settings();
        $result = $conn->query("
            SELECT online_payment_enabled, online_payment_label, online_payment_qr_path, updated_by_user_id, updated_at
            FROM financepaymentsettingstbl
            WHERE setting_id = 1
            LIMIT 1
        ");
        $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
        if (!$row) {
            return $defaults;
        }

        $settings = $defaults;
        $settings['online_payment_enabled'] = (int)($row['online_payment_enabled'] ?? 0) === 1;
        $settings['online_payment_label'] = $defaults['online_payment_label'];
        $settings['online_payment_qr_path'] = trim((string)($row['online_payment_qr_path'] ?? '')) ?: $defaults['online_payment_qr_path'];
        $settings['updated_by_user_id'] = (string)($row['updated_by_user_id'] ?? '');
        $settings['updated_at'] = (string)($row['updated_at'] ?? '');
        return $settings;
    }
}

if (!function_exists('fps_store_uploaded_payment_qr')) {
    function fps_store_uploaded_payment_qr(array $file): string
    {
        $errorCode = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode === UPLOAD_ERR_NO_FILE) {
            return '';
        }

        $uploadError = app_upload_validate_file($file, 'admin', 'Payment QR image', true);
        if ($uploadError !== null) {
            throw new RuntimeException($uploadError);
        }

        $tmpName = trim((string)($file['tmp_name'] ?? ''));
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('Invalid upload source for payment QR image.');
        }

        $originalName = (string)($file['name'] ?? '');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new RuntimeException('Payment QR image must be JPG, JPEG, PNG, or WEBP.');
        }

        $imageInfo = @getimagesize($tmpName);
        if (!is_array($imageInfo)) {
            throw new RuntimeException('Uploaded QR file must be a valid image.');
        }

        $baseDir = realpath(__DIR__ . '/../../');
        if ($baseDir === false) {
            throw new RuntimeException('Server path resolution failed.');
        }

        $relativeDir = '/UnifiedFileAttachment/FinancePayments/QR';
        $targetDir = rtrim($baseDir, "/\\") . $relativeDir;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new RuntimeException('Unable to prepare the QR upload directory.');
        }

        $targetName = 'payment_qr_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $targetPath = rtrim($targetDir, "/\\") . '/' . $targetName;
        if (!move_uploaded_file($tmpName, $targetPath)) {
            throw new RuntimeException('Unable to save the uploaded QR image.');
        }
        @chmod($targetPath, 0664);

        return $relativeDir . '/' . $targetName;
    }
}

if (!function_exists('fps_save_payment_settings')) {
    function fps_save_payment_settings(mysqli $conn, array $input, array $files, string $userId = ''): array
    {
        fps_ensure_payment_settings_table($conn);
        $before = fps_resolve_payment_settings($conn);

        $label = 'GCash';

        $qrPath = trim((string)($before['online_payment_qr_path'] ?? ''));
        $uploadedQrPath = fps_store_uploaded_payment_qr((array)($files['online_payment_qr'] ?? []));
        if ($uploadedQrPath !== '') {
            $qrPath = $uploadedQrPath;
        }
        if ($qrPath === '') {
            $qrPath = '/Images/GCASH_QR.jpg';
        }

        $enabled = !empty($input['online_payment_enabled']) ? 1 : 0;
        $stmt = $conn->prepare("
            INSERT INTO financepaymentsettingstbl (
                setting_id,
                online_payment_enabled,
                online_payment_label,
                online_payment_qr_path,
                updated_by_user_id
            ) VALUES (1, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                online_payment_enabled = VALUES(online_payment_enabled),
                online_payment_label = VALUES(online_payment_label),
                online_payment_qr_path = VALUES(online_payment_qr_path),
                updated_by_user_id = VALUES(updated_by_user_id),
                updated_at = CURRENT_TIMESTAMP
        ");
        if (!$stmt) {
            throw new RuntimeException('Unable to prepare finance payment settings save.');
        }
        $stmt->bind_param('isss', $enabled, $label, $qrPath, $userId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Unable to save finance payment settings.');
        }
        $stmt->close();

        return [
            'before' => $before,
            'after' => fps_resolve_payment_settings($conn),
        ];
    }
}

if (!function_exists('fps_public_asset_url')) {
    function fps_public_asset_url(string $publicPath, string $baseUrl = ''): string
    {
        $path = trim($publicPath);
        if ($path === '') {
            $path = '/Images/GCASH_QR.jpg';
        }
        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }

        $root = trim($baseUrl) !== '' ? rtrim($baseUrl, '/') : (function_exists('appRootPath') ? rtrim(appRootPath(), '/') : '');
        return $root . '/' . ltrim($path, '/');
    }
}
