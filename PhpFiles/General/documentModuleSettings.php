<?php
declare(strict_types=1);

require_once __DIR__ . '/uploadLimits.php';

if (!function_exists('dms_module_catalog')) {
    function dms_module_catalog(): array
    {
        return [
            'issuance' => [
                'key' => 'issuance',
                'label' => 'Barangay Issuance Settings',
                'description' => 'Manage signature assets used on certificate issuance previews and generated documents.',
                'applies_to' => 'Certificate requests, certificate previews, and generated issuance documents.',
                'back_href' => 'Admin-End/Certificates/CertificateTracker.php?filter_document=__certificates__',
                'signatories' => [
                    'punong' => [
                        'label' => 'Punong Barangay',
                        'source' => 'seat_punong',
                        'signature_help' => 'Shown on the certificate signatory block.',
                    ],
                ],
            ],
            'monitoring' => [
                'key' => 'monitoring',
                'label' => 'Barangay Monitoring Settings',
                'description' => 'Manage signatories used on clearance issuance and monitoring-related generated documents.',
                'applies_to' => 'General clearances, tricycle clearances, business permit clearances, and related previews.',
                'back_href' => 'Admin-End/BusinessMonitoring.php',
                'signatories' => [
                    'punong' => [
                        'label' => 'Punong Barangay',
                        'source' => 'seat_punong',
                        'signature_help' => 'Shown on the clearance signatory block.',
                    ],
                    'monitoring_head' => [
                        'label' => 'Monitoring Head',
                        'source' => 'manual',
                        'default_name' => 'MR. JOSEPH C. PATRICIO',
                        'default_title' => 'Head, Monitoring & Collection Dept.',
                        'signature_help' => 'Shown on business and monitoring clearance signatory blocks.',
                    ],
                ],
            ],
            'barangay_id' => [
                'key' => 'barangay_id',
                'label' => 'Barangay ID Settings',
                'description' => 'Manage the Barangay ID template editor, uploaded front/back assets, and the signatory asset used on the generated card.',
                'applies_to' => 'Barangay ID preview and generated digital/print-ready ID output.',
                'back_href' => 'Admin-End/Certificates/CertificateTracker.php?entry=id_issuance',
                'signatories' => [
                    'punong' => [
                        'label' => 'Punong Barangay',
                        'source' => 'seat_punong',
                        'signature_help' => 'Shown on the back of the Barangay ID card.',
                    ],
                ],
            ],
        ];
    }
}

if (!function_exists('dms_get_module_config')) {
    function dms_get_module_config(string $moduleKey): array
    {
        $catalog = dms_module_catalog();
        if (!isset($catalog[$moduleKey])) {
            throw new InvalidArgumentException('Unknown document settings module.');
        }

        return $catalog[$moduleKey];
    }
}

if (!function_exists('dms_db_table_exists')) {
    function dms_db_table_exists(mysqli $conn, string $table): bool
    {
        $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($tableSafe === '') {
            return false;
        }

        $tableEsc = $conn->real_escape_string($tableSafe);
        $result = $conn->query("SHOW TABLES LIKE '{$tableEsc}'");
        return $result instanceof mysqli_result && $result->num_rows > 0;
    }
}

if (!function_exists('dms_db_column_exists')) {
    function dms_db_column_exists(mysqli $conn, string $table, string $column): bool
    {
        $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($tableSafe === '') {
            return false;
        }

        $columnEsc = $conn->real_escape_string($column);
        $result = $conn->query("SHOW COLUMNS FROM {$tableSafe} LIKE '{$columnEsc}'");
        return $result instanceof mysqli_result && $result->num_rows > 0;
    }
}

if (!function_exists('dms_format_official_display_name')) {
    function dms_format_official_display_name(
        string $firstName,
        string $middleName,
        string $lastName,
        string $suffix = '',
        bool $prefixHonorific = false
    ): string {
        $firstName = trim($firstName);
        $middleName = trim($middleName);
        $lastName = trim($lastName);
        $suffix = trim($suffix);

        $middleInitial = '';
        if ($middleName !== '') {
            if (function_exists('mb_substr')) {
                $middleInitial = mb_substr($middleName, 0, 1, 'UTF-8');
            } else {
                $middleInitial = substr($middleName, 0, 1);
            }
            $middleInitial = strtoupper((string)$middleInitial) . '.';
        }

        $parts = array_values(array_filter([
            $prefixHonorific ? 'Hon.' : '',
            $firstName,
            $middleInitial,
            $lastName,
            $suffix,
        ], static fn($value): bool => trim((string)$value) !== ''));

        return trim(implode(' ', $parts));
    }
}

if (!function_exists('dms_current_barangay_signatories')) {
    function dms_current_barangay_signatories(mysqli $conn): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $fallback = [
            'punong' => [
                'name' => 'HON. GLENN S. EVANGELISTA',
                'title' => 'Punong Barangay',
            ],
            'secretary' => [
                'name' => 'MINERVA D. QUITA',
                'title' => 'Barangay Secretary',
            ],
        ];

        if (!dms_db_table_exists($conn, 'officialinformationtbl')) {
            $cache = $fallback;
            return $cache;
        }

        $resolved = $fallback;
        $positionField = dms_db_column_exists($conn, 'officialinformationtbl', 'position_access')
            ? 'COALESCE(oi.position_access, oi.role_access)'
            : 'oi.role_access';
        $officialsResult = $conn->query("
            SELECT
                oi.official_id,
                {$positionField} AS position_access,
                oi.firstname,
                oi.middlename,
                oi.lastname,
                oi.suffix
            FROM officialinformationtbl oi
            WHERE {$positionField} IN ('Barangay Chairman', 'Barangay Secretary', 'Punong Barangay', 'Barangay Captain')
            ORDER BY oi.official_id DESC
        ");
        if ($officialsResult instanceof mysqli_result) {
            while ($row = $officialsResult->fetch_assoc()) {
                $row = function_exists('pii_decrypt_official_row') ? (pii_decrypt_official_row($row) ?? $row) : $row;
                $positionAccess = strtolower(trim((string)($row['position_access'] ?? '')));
                if ($positionAccess === '') {
                    continue;
                }
                $fullName = dms_format_official_display_name(
                    (string)($row['firstname'] ?? ''),
                    (string)($row['middlename'] ?? ''),
                    (string)($row['lastname'] ?? ''),
                    (string)($row['suffix'] ?? '')
                );
                if ($fullName === '') {
                    continue;
                }

                if (
                    !isset($resolved['_from_position_punong'])
                    && in_array($positionAccess, ['barangay chairman', 'punong barangay', 'barangay captain'], true)
                ) {
                    $resolved['punong'] = [
                        'name' => dms_format_official_display_name(
                            (string)($row['firstname'] ?? ''),
                            (string)($row['middlename'] ?? ''),
                            (string)($row['lastname'] ?? ''),
                            (string)($row['suffix'] ?? ''),
                            true
                        ),
                        'title' => 'Punong Barangay',
                    ];
                    $resolved['_from_position_punong'] = true;
                }

                if (!isset($resolved['_from_position_secretary']) && $positionAccess === 'barangay secretary') {
                    $resolved['secretary'] = [
                        'name' => $fullName,
                        'title' => 'Barangay Secretary',
                    ];
                    $resolved['_from_position_secretary'] = true;
                }
            }
            $officialsResult->free();
        }

        if (
            (!isset($resolved['_from_position_punong']) || !isset($resolved['_from_position_secretary']))
            && dms_db_table_exists($conn, 'barangaycounciltbl')
        ) {
            $result = $conn->query("
                SELECT bc.seat_name, oi.firstname, oi.middlename, oi.lastname, oi.suffix
                FROM barangaycounciltbl bc
                LEFT JOIN officialinformationtbl oi
                    ON oi.official_id = bc.current_official_id
                WHERE bc.is_active = 1
                  AND bc.current_official_id IS NOT NULL
                ORDER BY bc.sort_order, bc.council_id
            ");
            if ($result instanceof mysqli_result) {
                while ($row = $result->fetch_assoc()) {
                    $row = function_exists('pii_decrypt_official_row') ? (pii_decrypt_official_row($row) ?? $row) : $row;
                    $seatName = trim((string)($row['seat_name'] ?? ''));
                    if ($seatName === '') {
                        continue;
                    }

                    $fullName = dms_format_official_display_name(
                        (string)($row['firstname'] ?? ''),
                        (string)($row['middlename'] ?? ''),
                        (string)($row['lastname'] ?? ''),
                        (string)($row['suffix'] ?? '')
                    );
                    if ($fullName === '') {
                        continue;
                    }

                    $seatLower = strtolower($seatName);
                    if (
                        !isset($resolved['_from_position_punong'])
                        && (
                            strpos($seatLower, 'punong barangay') !== false
                            || strpos($seatLower, 'barangay captain') !== false
                            || $seatLower === 'barangay chairman'
                        )
                    ) {
                        $resolved['punong'] = [
                            'name' => dms_format_official_display_name(
                                (string)($row['firstname'] ?? ''),
                                (string)($row['middlename'] ?? ''),
                                (string)($row['lastname'] ?? ''),
                                (string)($row['suffix'] ?? ''),
                                true
                            ),
                            'title' => 'Punong Barangay',
                        ];
                        $resolved['_from_position_punong'] = true;
                        continue;
                    }

                    if (!isset($resolved['_from_position_secretary']) && $seatLower === 'barangay secretary') {
                        $resolved['secretary'] = [
                            'name' => $fullName,
                            'title' => 'Barangay Secretary',
                        ];
                        $resolved['_from_position_secretary'] = true;
                    }
                }
                $result->free();
            }
        }

        unset($resolved['_from_position_punong'], $resolved['_from_position_secretary']);
        $cache = $resolved;
        return $cache;
    }
}

if (!function_exists('dms_ensure_settings_table')) {
    function dms_ensure_settings_table(mysqli $conn): void
    {
        static $done = false;
        if ($done) {
            return;
        }

        $conn->query("
            CREATE TABLE IF NOT EXISTS documentmodulesettingstbl (
                setting_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                module_key VARCHAR(32) NOT NULL,
                signatory_key VARCHAR(32) NOT NULL,
                signatory_name VARCHAR(191) DEFAULT NULL,
                signatory_title VARCHAR(191) DEFAULT NULL,
                signature_path VARCHAR(255) DEFAULT NULL,
                updated_by_user_id VARCHAR(12) DEFAULT NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (setting_id),
                UNIQUE KEY uq_document_module_signatory (module_key, signatory_key),
                KEY idx_document_module_settings_module (module_key),
                KEY idx_document_module_settings_updated (updated_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $done = true;
    }
}

if (!function_exists('dms_fetch_module_setting_rows')) {
    function dms_fetch_module_setting_rows(mysqli $conn, string $moduleKey): array
    {
        dms_ensure_settings_table($conn);

        $rows = [];
        $stmt = $conn->prepare("
            SELECT module_key, signatory_key, signatory_name, signatory_title, signature_path, updated_by_user_id, updated_at
            FROM documentmodulesettingstbl
            WHERE module_key = ?
        ");
        if (!$stmt) {
            return $rows;
        }

        $stmt->bind_param('s', $moduleKey);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $rows[(string)($row['signatory_key'] ?? '')] = $row;
        }
        $stmt->close();

        return $rows;
    }
}

if (!function_exists('dms_signature_public_path_to_disk')) {
    function dms_signature_public_path_to_disk(string $publicPath): string
    {
        $normalized = str_replace('\\', '/', trim($publicPath));
        if ($normalized === '' || strpos($normalized, '/UnifiedFileAttachment/') !== 0) {
            return '';
        }

        $baseDir = realpath(__DIR__ . '/../../');
        if ($baseDir === false) {
            return '';
        }

        return $baseDir . $normalized;
    }
}

if (!function_exists('dms_resolve_module_signatories')) {
    function dms_resolve_module_signatories(mysqli $conn, string $moduleKey): array
    {
        $config = dms_get_module_config($moduleKey);
        $storedRows = dms_fetch_module_setting_rows($conn, $moduleKey);
        $seatSignatories = dms_current_barangay_signatories($conn);
        $resolved = [];

        foreach ((array)($config['signatories'] ?? []) as $signatoryKey => $signatoryConfig) {
            $row = $storedRows[$signatoryKey] ?? [];
            $source = (string)($signatoryConfig['source'] ?? 'manual');
            $defaultName = trim((string)($signatoryConfig['default_name'] ?? ''));
            $defaultTitle = trim((string)($signatoryConfig['default_title'] ?? ''));
            $name = trim((string)($row['signatory_name'] ?? ''));
            $title = trim((string)($row['signatory_title'] ?? ''));

            if ($source === 'seat_punong') {
                $name = trim((string)($seatSignatories['punong']['name'] ?? $defaultName));
                $title = trim((string)($seatSignatories['punong']['title'] ?? ($defaultTitle !== '' ? $defaultTitle : 'Punong Barangay')));
            } elseif ($source === 'seat_secretary') {
                $name = trim((string)($seatSignatories['secretary']['name'] ?? $defaultName));
                $title = trim((string)($seatSignatories['secretary']['title'] ?? ($defaultTitle !== '' ? $defaultTitle : 'Barangay Secretary')));
            } else {
                if ($name === '') {
                    $name = $defaultName;
                }
                if ($title === '') {
                    $title = $defaultTitle;
                }
            }

            $resolved[$signatoryKey] = [
                'signatory_key' => $signatoryKey,
                'label' => (string)($signatoryConfig['label'] ?? $signatoryKey),
                'source' => $source,
                'name' => $name,
                'title' => $title,
                'signature_path' => trim((string)($row['signature_path'] ?? '')),
                'signature_help' => trim((string)($signatoryConfig['signature_help'] ?? '')),
                'default_name' => $defaultName,
                'default_title' => $defaultTitle,
                'updated_at' => trim((string)($row['updated_at'] ?? '')),
                'updated_by_user_id' => trim((string)($row['updated_by_user_id'] ?? '')),
            ];
        }

        return $resolved;
    }
}

if (!function_exists('dms_signature_upload_directory')) {
    function dms_signature_upload_directory(string $moduleKey): array
    {
        $baseDir = realpath(__DIR__ . '/../../');
        if ($baseDir === false) {
            throw new RuntimeException('Unable to resolve workspace path.');
        }

        $moduleSafe = preg_replace('/[^a-z0-9_-]/i', '', strtolower($moduleKey)) ?: 'module';
        $relativeDir = '/UnifiedFileAttachment/DocumentSettings/Signatures/' . $moduleSafe;
        return [
            'disk_dir' => $baseDir . $relativeDir,
            'public_dir' => $relativeDir,
        ];
    }
}

if (!function_exists('dms_detect_signature_extension')) {
    function dms_detect_signature_extension(string $tmpName, string $originalName = ''): string
    {
        $mime = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = (string)(finfo_file($finfo, $tmpName) ?: '');
                finfo_close($finfo);
            }
        }

        return match (strtolower($mime)) {
            'image/png' => 'png',
            'image/jpeg', 'image/jpg' => 'jpg',
            default => match (strtolower(pathinfo($originalName, PATHINFO_EXTENSION))) {
                'png' => 'png',
                'jpg', 'jpeg' => 'jpg',
                default => '',
            },
        };
    }
}

if (!function_exists('dms_store_uploaded_signature')) {
    function dms_store_uploaded_signature(string $moduleKey, string $signatoryKey, array $file): string
    {
        $uploadError = app_upload_validate_file($file, 'admin', 'Signature image', false);
        if ($uploadError !== null) {
            throw new RuntimeException($uploadError);
        }

        $tmpName = trim((string)($file['tmp_name'] ?? ''));
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('Invalid upload source for signature image.');
        }

        $extension = dms_detect_signature_extension($tmpName, (string)($file['name'] ?? ''));
        if ($extension === '') {
            throw new RuntimeException('Signature image must be a PNG or JPG file.');
        }

        $dirs = dms_signature_upload_directory($moduleKey);
        if (!is_dir($dirs['disk_dir']) && !mkdir($dirs['disk_dir'], 0775, true) && !is_dir($dirs['disk_dir'])) {
            throw new RuntimeException('Unable to prepare the signature upload directory.');
        }

        $fileSafeSignatory = preg_replace('/[^a-z0-9_-]/i', '', strtolower($signatoryKey)) ?: 'signatory';
        $targetName = $fileSafeSignatory . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $targetDiskPath = rtrim($dirs['disk_dir'], '/') . '/' . $targetName;
        if (!move_uploaded_file($tmpName, $targetDiskPath)) {
            throw new RuntimeException('Unable to save the uploaded signature image.');
        }

        return rtrim($dirs['public_dir'], '/') . '/' . $targetName;
    }
}

if (!function_exists('dms_delete_signature_file')) {
    function dms_delete_signature_file(string $publicPath): void
    {
        $diskPath = dms_signature_public_path_to_disk($publicPath);
        if ($diskPath === '') {
            return;
        }
        if (is_file($diskPath)) {
            @unlink($diskPath);
        }
    }
}

if (!function_exists('dms_upsert_signatory_setting')) {
    function dms_upsert_signatory_setting(
        mysqli $conn,
        string $moduleKey,
        string $signatoryKey,
        string $name,
        string $title,
        string $signaturePath,
        string $updatedByUserId
    ): void {
        dms_ensure_settings_table($conn);

        $stmt = $conn->prepare("
            INSERT INTO documentmodulesettingstbl (
                module_key,
                signatory_key,
                signatory_name,
                signatory_title,
                signature_path,
                updated_by_user_id
            ) VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                signatory_name = VALUES(signatory_name),
                signatory_title = VALUES(signatory_title),
                signature_path = VALUES(signature_path),
                updated_by_user_id = VALUES(updated_by_user_id),
                updated_at = CURRENT_TIMESTAMP
        ");
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare document settings update.');
        }

        $stmt->bind_param('ssssss', $moduleKey, $signatoryKey, $name, $title, $signaturePath, $updatedByUserId);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('dms_save_module_signatories')) {
    function dms_save_module_signatories(mysqli $conn, string $moduleKey, array $post, array $files, string $updatedByUserId): array
    {
        $config = dms_get_module_config($moduleKey);
        $existingRows = dms_fetch_module_setting_rows($conn, $moduleKey);
        $resolvedBefore = dms_resolve_module_signatories($conn, $moduleKey);

        foreach ((array)($config['signatories'] ?? []) as $signatoryKey => $signatoryConfig) {
            $storedRow = $existingRows[$signatoryKey] ?? [];
            $source = (string)($signatoryConfig['source'] ?? 'manual');
            $nameField = 'signatory_name_' . $signatoryKey;
            $titleField = 'signatory_title_' . $signatoryKey;
            $removeField = 'remove_signature_' . $signatoryKey;
            $fileField = 'signature_file_' . $signatoryKey;

            $storedName = trim((string)($storedRow['signatory_name'] ?? ''));
            $storedTitle = trim((string)($storedRow['signatory_title'] ?? ''));
            $storedSignaturePath = trim((string)($storedRow['signature_path'] ?? ''));
            $name = $source === 'manual'
                ? trim((string)($post[$nameField] ?? $storedName))
                : $storedName;
            $title = $source === 'manual'
                ? trim((string)($post[$titleField] ?? $storedTitle))
                : $storedTitle;
            $signaturePath = $storedSignaturePath;

            if (!empty($post[$removeField])) {
                dms_delete_signature_file($storedSignaturePath);
                $signaturePath = '';
            }

            if (isset($files[$fileField]) && is_array($files[$fileField])) {
                $uploadErrorCode = (int)($files[$fileField]['error'] ?? UPLOAD_ERR_NO_FILE);
                if ($uploadErrorCode !== UPLOAD_ERR_NO_FILE) {
                    $newPath = dms_store_uploaded_signature($moduleKey, $signatoryKey, $files[$fileField]);
                    if ($storedSignaturePath !== '' && $storedSignaturePath !== $newPath) {
                        dms_delete_signature_file($storedSignaturePath);
                    }
                    $signaturePath = $newPath;
                }
            }

            if ($source === 'manual' && $name === '') {
                $name = trim((string)($signatoryConfig['default_name'] ?? ''));
            }
            if ($source === 'manual' && $title === '') {
                $title = trim((string)($signatoryConfig['default_title'] ?? ''));
            }

            dms_upsert_signatory_setting(
                $conn,
                $moduleKey,
                $signatoryKey,
                $name,
                $title,
                $signaturePath,
                $updatedByUserId
            );
        }

        $resolvedAfter = dms_resolve_module_signatories($conn, $moduleKey);
        return [
            'before' => $resolvedBefore,
            'after' => $resolvedAfter,
        ];
    }
}

if (!function_exists('dms_max_updated_meta')) {
    function dms_max_updated_meta(array $rows): array
    {
        $latest = [
            'updated_at' => '',
            'updated_by_user_id' => '',
        ];

        foreach ($rows as $row) {
            $updatedAt = trim((string)($row['updated_at'] ?? ''));
            if ($updatedAt === '') {
                continue;
            }
            if ($latest['updated_at'] === '' || strcmp($updatedAt, $latest['updated_at']) > 0) {
                $latest = [
                    'updated_at' => $updatedAt,
                    'updated_by_user_id' => trim((string)($row['updated_by_user_id'] ?? '')),
                ];
            }
        }

        return $latest;
    }
}

if (!function_exists('dms_module_key_for_document_type')) {
    function dms_module_key_for_document_type(string $documentType): string
    {
        $normalized = strtolower(trim($documentType));
        $token = preg_replace('/[^a-z0-9]+/', '', $normalized) ?? '';
        if ($token === 'barangayid' || str_contains($normalized, 'barangay id')) {
            return 'barangay_id';
        }

        if (
            str_contains($normalized, 'clearance')
            || in_array($token, [
                'generalclearance',
                'tricycleclearance',
                'barangayclearanceforbusinesspermit',
                'businessclearance',
                'clearanceforbusinesspermit',
            ], true)
        ) {
            return 'monitoring';
        }

        return 'issuance';
    }
}

if (!function_exists('dms_module_asset_public_path_to_disk')) {
    function dms_module_asset_public_path_to_disk(string $storedPath): string
    {
        $normalized = str_replace('\\', '/', trim($storedPath));
        if ($normalized === '') {
            return '';
        }

        $baseDir = realpath(__DIR__ . '/../../');
        if ($baseDir === false) {
            return '';
        }

        if (strpos($normalized, $baseDir) === 0 && is_file($normalized)) {
            return $normalized;
        }

        if (preg_match('/^https?:\/\//i', $normalized)) {
            $urlPath = parse_url($normalized, PHP_URL_PATH);
            $normalized = is_string($urlPath) ? $urlPath : '';
        }

        $marker = '/UnifiedFileAttachment/';
        $markerPos = strpos($normalized, $marker);
        if ($markerPos !== false) {
            $normalized = substr($normalized, $markerPos);
        }

        if ($normalized === '') {
            return '';
        }

        if ($normalized[0] !== '/') {
            $normalized = '/' . $normalized;
        }

        $candidate = $baseDir . $normalized;
        return is_file($candidate) ? $candidate : '';
    }
}

if (!function_exists('dms_restore_template_blob')) {
    function dms_restore_template_blob(string $publicPath, string $blob): string
    {
        if ($blob === '' || strpos(str_replace('\\', '/', $publicPath), '/UnifiedFileAttachment/') === false) {
            return '';
        }
        $baseDir = realpath(__DIR__ . '/../../');
        if ($baseDir === false) {
            return '';
        }
        $normalized = str_replace('\\', '/', trim($publicPath));
        $markerPos = strpos($normalized, '/UnifiedFileAttachment/');
        $relative = $markerPos !== false ? substr($normalized, $markerPos) : '';
        if ($relative === '' || strtolower(pathinfo($relative, PATHINFO_EXTENSION)) !== 'png') {
            return '';
        }
        $target = $baseDir . $relative;
        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            return '';
        }
        if (!is_file($target) && file_put_contents($target, $blob, LOCK_EX) === false) {
            return '';
        }
        return is_file($target) ? $target : '';
    }
}

if (!function_exists('dms_json_encode_pretty')) {
    function dms_json_encode_pretty($value): string
    {
        $encoded = json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );
        return is_string($encoded) ? $encoded : '{}';
    }
}

if (!function_exists('dms_ensure_module_template_config_table')) {
    function dms_ensure_module_template_config_table(mysqli $conn): void
    {
        static $done = false;
        if ($done) {
            return;
        }

        $conn->query("
            CREATE TABLE IF NOT EXISTS documentmoduleconfigtbl (
                config_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                module_key VARCHAR(32) NOT NULL,
                template_front_path VARCHAR(255) DEFAULT NULL,
                template_back_path VARCHAR(255) DEFAULT NULL,
                template_front_blob LONGBLOB DEFAULT NULL,
                template_back_blob LONGBLOB DEFAULT NULL,
                layout_json LONGTEXT DEFAULT NULL,
                sample_data_json LONGTEXT DEFAULT NULL,
                updated_by_user_id VARCHAR(12) DEFAULT NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (config_id),
                UNIQUE KEY uq_document_module_config (module_key),
                KEY idx_document_module_config_updated (updated_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        if (!dms_db_column_exists($conn, 'documentmoduleconfigtbl', 'template_front_blob')) {
            $conn->query("ALTER TABLE documentmoduleconfigtbl ADD COLUMN template_front_blob LONGBLOB NULL AFTER template_back_path");
        }
        if (!dms_db_column_exists($conn, 'documentmoduleconfigtbl', 'template_back_blob')) {
            $conn->query("ALTER TABLE documentmoduleconfigtbl ADD COLUMN template_back_blob LONGBLOB NULL AFTER template_front_blob");
        }

        $done = true;
    }
}

if (!function_exists('dms_fetch_module_template_config_row')) {
    function dms_fetch_module_template_config_row(mysqli $conn, string $moduleKey): array
    {
        dms_ensure_module_template_config_table($conn);

        $stmt = $conn->prepare("
            SELECT
                module_key,
                template_front_path,
                template_back_path,
                template_front_blob,
                template_back_blob,
                layout_json,
                sample_data_json,
                updated_by_user_id,
                updated_at
            FROM documentmoduleconfigtbl
            WHERE module_key = ?
            LIMIT 1
        ");
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('s', $moduleKey);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result instanceof mysqli_result ? ($result->fetch_assoc() ?: []) : [];
        $stmt->close();

        return is_array($row) ? $row : [];
    }
}

if (!function_exists('dms_template_upload_directory')) {
    function dms_template_upload_directory(string $moduleKey): array
    {
        $baseDir = realpath(__DIR__ . '/../../');
        if ($baseDir === false) {
            throw new RuntimeException('Unable to resolve workspace path.');
        }

        $moduleSafe = preg_replace('/[^a-z0-9_-]/i', '', strtolower($moduleKey)) ?: 'module';
        $relativeDir = '/UnifiedFileAttachment/DocumentSettings/Templates/' . $moduleSafe;
        return [
            'disk_dir' => $baseDir . $relativeDir,
            'public_dir' => $relativeDir,
        ];
    }
}

if (!function_exists('dms_detect_png_extension')) {
    function dms_detect_png_extension(string $tmpName, string $originalName = ''): string
    {
        $mime = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = (string)(finfo_file($finfo, $tmpName) ?: '');
                finfo_close($finfo);
            }
        }

        if (strtolower($mime) === 'image/png') {
            return 'png';
        }

        return strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) === 'png' ? 'png' : '';
    }
}

if (!function_exists('dms_store_uploaded_template_png')) {
    function dms_store_uploaded_template_png(string $moduleKey, string $assetKey, array $file): string
    {
        $uploadError = app_upload_validate_file($file, 'admin', 'Template PNG', false);
        if ($uploadError !== null) {
            throw new RuntimeException($uploadError);
        }

        $tmpName = trim((string)($file['tmp_name'] ?? ''));
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('Invalid upload source for template PNG.');
        }

        $extension = dms_detect_png_extension($tmpName, (string)($file['name'] ?? ''));
        if ($extension !== 'png') {
            throw new RuntimeException('Template upload must be a PNG image.');
        }

        $dirs = dms_template_upload_directory($moduleKey);
        if (!is_dir($dirs['disk_dir']) && !mkdir($dirs['disk_dir'], 0775, true) && !is_dir($dirs['disk_dir'])) {
            throw new RuntimeException('Unable to prepare the template upload directory.');
        }

        $assetSafe = preg_replace('/[^a-z0-9_-]/i', '', strtolower($assetKey)) ?: 'template';
        $targetName = $assetSafe . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.png';
        $targetDiskPath = rtrim($dirs['disk_dir'], '/') . '/' . $targetName;
        if (!move_uploaded_file($tmpName, $targetDiskPath)) {
            throw new RuntimeException('Unable to save the uploaded template image.');
        }

        return rtrim($dirs['public_dir'], '/') . '/' . $targetName;
    }
}

if (!function_exists('dms_delete_module_asset_file')) {
    function dms_delete_module_asset_file(string $publicPath): void
    {
        $diskPath = dms_module_asset_public_path_to_disk($publicPath);
        if ($diskPath !== '' && is_file($diskPath)) {
            @unlink($diskPath);
        }
    }
}

if (!function_exists('dms_barangay_id_default_template_paths')) {
    function dms_barangay_id_default_template_paths(): array
    {
        return [
            'front' => '/Resident-End/Certificates/BarangayID/FRONT_EMPTY.png',
            'back' => '/Resident-End/Certificates/BarangayID/BACK_EMPTY.png',
        ];
    }
}

if (!function_exists('dms_barangay_id_default_sample_data')) {
    function dms_barangay_id_default_sample_data(): array
    {
        return [
            'cardFullName' => 'DELA CRUZ, JUAN S.',
            'cardFullAddress' => 'AREA 1, BARANGAY SAN JOSE, RODRIGUEZ, RIZAL',
            'cardBirthdate' => '04/16/1998',
            'cardBirthplace' => 'RODRIGUEZ, RIZAL',
            'cardSex' => 'MALE',
            'cardContactNumber' => '09171234567',
            'cardEmergencyName' => 'DELA CRUZ, MARIA L.',
            'cardEmergencyAddress' => 'AREA 1, BARANGAY SAN JOSE, RODRIGUEZ, RIZAL',
            'cardEmergencyContact' => '09179876543',
            'cardNumber' => 'A2026-0001',
            'validUntil' => '07/13/2028',
            'validityNotice' => 'This ID is valid until 07/13/2028 except when the holder requests for a new one.',
        ];
    }
}

if (!function_exists('dms_barangay_id_default_layout')) {
    function dms_barangay_id_default_layout(): array
    {
        $layout = [
            'version' => 1,
            'page' => [
                'width_mm' => 85.6,
                'height_mm' => 54.1,
            ],
            'fields' => [
                [
                    'id' => 'front_photo',
                    'label' => 'Resident Photo',
                    'type' => 'image',
                    'source' => 'photoUrl',
                    'side' => 'front',
                    'x' => 7.9,
                    'y' => 22.1,
                    'w' => 22.0,
                    'h' => 22.0,
                    'fit' => 'cover',
                    'z' => 2,
                ],
                [
                    'id' => 'front_name_label',
                    'label' => 'Label: Name',
                    'type' => 'label',
                    'text' => 'Name',
                    'side' => 'front',
                    'x' => 32.2,
                    'y' => 24.08,
                    'w' => 10.0,
                    'h' => 3.2,
                    'align' => 'left',
                    'fontStyle' => 'I',
                    'fontSize' => 5.1,
                    'minFontSize' => 4.0,
                    'color' => '#111111',
                    'z' => 2,
                ],
                [
                    'id' => 'front_name_value',
                    'label' => 'Full Name',
                    'type' => 'text',
                    'source' => 'cardFullName',
                    'side' => 'front',
                    'x' => 32.2,
                    'y' => 25.8,
                    'w' => 44.8,
                    'h' => 4.8,
                    'align' => 'left',
                    'fontStyle' => 'B',
                    'fontSize' => 7.2,
                    'minFontSize' => 4.6,
                    'uppercase' => true,
                    'z' => 2,
                ],
                [
                    'id' => 'front_address_label',
                    'label' => 'Label: Address',
                    'type' => 'label',
                    'text' => 'Address',
                    'side' => 'front',
                    'x' => 32.2,
                    'y' => 30.58,
                    'w' => 13.5,
                    'h' => 3.2,
                    'align' => 'left',
                    'fontStyle' => 'I',
                    'fontSize' => 5.1,
                    'minFontSize' => 4.0,
                    'color' => '#111111',
                    'z' => 2,
                ],
                [
                    'id' => 'front_address_value',
                    'label' => 'Address',
                    'type' => 'text',
                    'source' => 'cardFullAddress',
                    'side' => 'front',
                    'x' => 31.2,
                    'y' => 32.78,
                    'w' => 44.8,
                    'h' => 6.0,
                    'align' => 'left',
                    'fontStyle' => 'B',
                    'fontSize' => 5.6,
                    'minFontSize' => 3.2,
                    'uppercase' => true,
                    'multiline' => true,
                    'maxLines' => 2,
                    'z' => 2,
                ],
                [
                    'id' => 'front_birthdate_label',
                    'label' => 'Label: Date of Birth',
                    'type' => 'label',
                    'text' => 'Date of Birth',
                    'side' => 'front',
                    'x' => 32.2,
                    'y' => 38.48,
                    'w' => 20.0,
                    'h' => 3.2,
                    'align' => 'left',
                    'fontStyle' => 'I',
                    'fontSize' => 5.1,
                    'minFontSize' => 4.0,
                    'color' => '#111111',
                    'z' => 2,
                ],
                [
                    'id' => 'front_birthdate_value',
                    'label' => 'Birthdate',
                    'type' => 'text',
                    'source' => 'cardBirthdate',
                    'side' => 'front',
                    'x' => 32.2,
                    'y' => 40.78,
                    'w' => 20.5,
                    'h' => 4.4,
                    'align' => 'left',
                    'fontStyle' => 'B',
                    'fontSize' => 6.4,
                    'minFontSize' => 4.4,
                    'uppercase' => true,
                    'z' => 2,
                ],
                [
                    'id' => 'front_sex_label',
                    'label' => 'Label: Sex',
                    'type' => 'label',
                    'text' => 'Sex',
                    'side' => 'front',
                    'x' => 57.2,
                    'y' => 38.48,
                    'w' => 10.0,
                    'h' => 3.2,
                    'align' => 'left',
                    'fontStyle' => 'I',
                    'fontSize' => 5.1,
                    'minFontSize' => 4.0,
                    'color' => '#111111',
                    'z' => 2,
                ],
                [
                    'id' => 'front_sex_value',
                    'label' => 'Sex',
                    'type' => 'text',
                    'source' => 'cardSex',
                    'side' => 'front',
                    'x' => 57.2,
                    'y' => 40.78,
                    'w' => 19.5,
                    'h' => 4.4,
                    'align' => 'left',
                    'fontStyle' => 'B',
                    'fontSize' => 6.4,
                    'minFontSize' => 4.4,
                    'uppercase' => true,
                    'z' => 2,
                ],
                [
                    'id' => 'front_birthplace_label',
                    'label' => 'Label: Place of Birth',
                    'type' => 'label',
                    'text' => 'Place of Birth',
                    'side' => 'front',
                    'x' => 32.2,
                    'y' => 44.78,
                    'w' => 20.0,
                    'h' => 3.2,
                    'align' => 'left',
                    'fontStyle' => 'I',
                    'fontSize' => 5.1,
                    'minFontSize' => 4.0,
                    'color' => '#111111',
                    'z' => 2,
                ],
                [
                    'id' => 'front_birthplace_value',
                    'label' => 'Birthplace',
                    'type' => 'text',
                    'source' => 'cardBirthplace',
                    'side' => 'front',
                    'x' => 32.2,
                    'y' => 46.98,
                    'w' => 44.8,
                    'h' => 4.2,
                    'align' => 'left',
                    'fontStyle' => 'B',
                    'fontSize' => 5.5,
                    'minFontSize' => 4.0,
                    'uppercase' => true,
                    'z' => 2,
                ],
                [
                    'id' => 'front_valid_until_value',
                    'label' => 'Valid Until',
                    'type' => 'text',
                    'source' => 'validUntil',
                    'side' => 'front',
                    'x' => 6.0,
                    'y' => 44.78,
                    'w' => 28.6,
                    'h' => 4.2,
                    'align' => 'left',
                    'fontStyle' => 'B',
                    'fontSize' => 4.8,
                    'minFontSize' => 3.7,
                    'uppercase' => true,
                    'z' => 2,
                ],
                [
                    'id' => 'front_card_number_value',
                    'label' => 'Card Number',
                    'type' => 'text',
                    'source' => 'cardNumber',
                    'side' => 'front',
                    'x' => 6.4,
                    'y' => 49.58,
                    'w' => 28.4,
                    'h' => 4.4,
                    'align' => 'left',
                    'fontStyle' => 'B',
                    'fontSize' => 6.8,
                    'minFontSize' => 4.4,
                    'uppercase' => true,
                    'color' => '#c62828',
                    'z' => 2,
                ],
                [
                    'id' => 'back_card_number_value',
                    'label' => 'Card Number (Back)',
                    'type' => 'text',
                    'source' => 'cardNumber',
                    'side' => 'back',
                    'x' => 59.5,
                    'y' => 3.3,
                    'w' => 21.5,
                    'h' => 4.6,
                    'align' => 'right',
                    'fontStyle' => 'B',
                    'fontSize' => 7.6,
                    'minFontSize' => 5.0,
                    'uppercase' => true,
                    'color' => '#c62828',
                    'z' => 2,
                ],
                [
                    'id' => 'back_emergency_name_label',
                    'label' => 'Label: Emergency Name',
                    'type' => 'label',
                    'text' => 'Name',
                    'side' => 'back',
                    'x' => 6.9,
                    'y' => 17.5,
                    'w' => 10.0,
                    'h' => 3.0,
                    'align' => 'left',
                    'fontStyle' => 'I',
                    'fontSize' => 5.0,
                    'minFontSize' => 4.0,
                    'color' => '#111111',
                    'z' => 2,
                ],
                [
                    'id' => 'back_emergency_name_value',
                    'label' => 'Emergency Contact Name',
                    'type' => 'text',
                    'source' => 'cardEmergencyName',
                    'side' => 'back',
                    'x' => 6.9,
                    'y' => 19.7,
                    'w' => 33.0,
                    'h' => 4.6,
                    'align' => 'left',
                    'fontStyle' => 'B',
                    'fontSize' => 6.0,
                    'minFontSize' => 4.3,
                    'uppercase' => true,
                    'z' => 2,
                ],
                [
                    'id' => 'back_emergency_address_label',
                    'label' => 'Label: Emergency Address',
                    'type' => 'label',
                    'text' => 'Address',
                    'side' => 'back',
                    'x' => 6.9,
                    'y' => 23.8,
                    'w' => 12.0,
                    'h' => 3.0,
                    'align' => 'left',
                    'fontStyle' => 'I',
                    'fontSize' => 5.0,
                    'minFontSize' => 4.0,
                    'color' => '#111111',
                    'z' => 2,
                ],
                [
                    'id' => 'back_emergency_address_value',
                    'label' => 'Emergency Address',
                    'type' => 'text',
                    'source' => 'cardEmergencyAddress',
                    'side' => 'back',
                    'x' => 6.9,
                    'y' => 26.0,
                    'w' => 39.6,
                    'h' => 6.0,
                    'align' => 'left',
                    'fontStyle' => 'B',
                    'fontSize' => 5.0,
                    'minFontSize' => 3.2,
                    'uppercase' => true,
                    'multiline' => true,
                    'maxLines' => 2,
                    'z' => 2,
                ],
                [
                    'id' => 'back_emergency_contact_label',
                    'label' => 'Label: Emergency Contact',
                    'type' => 'label',
                    'text' => 'Contact',
                    'side' => 'back',
                    'x' => 6.9,
                    'y' => 30.0,
                    'w' => 12.0,
                    'h' => 3.0,
                    'align' => 'left',
                    'fontStyle' => 'I',
                    'fontSize' => 5.0,
                    'minFontSize' => 4.0,
                    'color' => '#111111',
                    'z' => 2,
                ],
                [
                    'id' => 'back_emergency_contact_value',
                    'label' => 'Emergency Contact Number',
                    'type' => 'text',
                    'source' => 'cardEmergencyContact',
                    'fallbackSource' => 'cardContactNumber',
                    'side' => 'back',
                    'x' => 6.9,
                    'y' => 32.2,
                    'w' => 22.0,
                    'h' => 4.4,
                    'align' => 'left',
                    'fontStyle' => 'B',
                    'fontSize' => 6.0,
                    'minFontSize' => 4.3,
                    'uppercase' => true,
                    'z' => 2,
                ],
                [
                    'id' => 'back_signature',
                    'label' => 'Signature',
                    'type' => 'image',
                    'source' => 'punongSignatorySignatureUrl',
                    'side' => 'back',
                    'x' => 9.1,
                    'y' => 38.2,
                    'w' => 30.8,
                    'h' => 8.2,
                    'fit' => 'contain',
                    'z' => 3,
                ],
                [
                    'id' => 'back_qr',
                    'label' => 'Verification QR',
                    'type' => 'qr',
                    'source' => 'qrUrl',
                    'side' => 'back',
                    'x' => 49.0,
                    'y' => 11.5,
                    'w' => 32.3,
                    'h' => 31.4,
                    'fit' => 'fill',
                    'z' => 2,
                ],
            ],
        ];
        $layout['fields'] = array_values(array_filter(
            (array)($layout['fields'] ?? []),
            static fn(array $field): bool => strtolower(trim((string)($field['type'] ?? ''))) !== 'label'
        ));
        return $layout;
    }
}

if (!function_exists('dms_normalize_bool')) {
    function dms_normalize_bool($value, bool $default = false): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (bool)$value;
        }
        $normalized = strtolower(trim((string)$value));
        if ($normalized === '') {
            return $default;
        }
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('dms_normalize_float_range')) {
    function dms_normalize_float_range($value, float $default, float $min, float $max): float
    {
        $parsed = is_numeric($value) ? (float)$value : $default;
        if ($parsed < $min) {
            return $min;
        }
        if ($parsed > $max) {
            return $max;
        }
        return round($parsed, 2);
    }
}

if (!function_exists('dms_normalize_int_range')) {
    function dms_normalize_int_range($value, int $default, int $min, int $max): int
    {
        $parsed = is_numeric($value) ? (int)$value : $default;
        if ($parsed < $min) {
            return $min;
        }
        if ($parsed > $max) {
            return $max;
        }
        return $parsed;
    }
}

if (!function_exists('dms_barangay_id_allowed_field_types')) {
    function dms_barangay_id_allowed_field_types(): array
    {
        return ['text', 'image', 'qr', 'signatory', 'cover'];
    }
}

if (!function_exists('dms_normalize_barangay_id_field')) {
    function dms_normalize_barangay_id_field(array $field, int $index = 0): array
    {
        $type = strtolower(trim((string)($field['type'] ?? 'text')));
        if (!in_array($type, dms_barangay_id_allowed_field_types(), true)) {
            $type = 'text';
        }

        $side = strtolower(trim((string)($field['side'] ?? 'front'))) === 'back' ? 'back' : 'front';
        $fieldId = trim((string)($field['id'] ?? ''));
        $fieldId = preg_replace('/[^a-z0-9_-]+/i', '_', strtolower($fieldId)) ?? '';
        if ($fieldId === '') {
            $fieldId = 'field_' . ($index + 1);
        }

        $align = strtolower(trim((string)($field['align'] ?? 'left')));
        if (!in_array($align, ['left', 'center', 'right'], true)) {
            $align = 'left';
        }

        $fontStyle = strtoupper(trim((string)($field['fontStyle'] ?? '')));
        if (!in_array($fontStyle, ['', 'B', 'I', 'BI', 'IB'], true)) {
            $fontStyle = 'B';
        }
        if ($fontStyle === 'IB') {
            $fontStyle = 'BI';
        }

        $color = trim((string)($field['color'] ?? '#111111'));
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            $color = '#111111';
        }

        $backgroundColor = trim((string)($field['backgroundColor'] ?? '#ffffff'));
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $backgroundColor)) {
            $backgroundColor = '#ffffff';
        }

        $fit = strtolower(trim((string)($field['fit'] ?? 'cover')));
        if (!in_array($fit, ['cover', 'contain', 'fill'], true)) {
            $fit = 'cover';
        }

        $source = trim((string)($field['source'] ?? ''));
        $freeSize = in_array($type, ['image', 'qr'], true)
            || ($type === 'text' && ($source === 'cardNumber' || str_contains($fieldId, 'card_number')));
        $baseW = dms_normalize_float_range($field['w'] ?? 10, 10.0, 1.2, 85.6);
        $baseH = dms_normalize_float_range($field['h'] ?? 4, 4.0, 1.0, 54.1);
        $defaultMaxW = $freeSize ? 85.6 : min(85.6, max($baseW * 1.75, $baseW + 14.0));
        $defaultMaxH = $freeSize ? 54.1 : min(54.1, max($baseH * 2.4, $baseH + 5.0));

        $normalized = [
            'id' => $fieldId,
            'label' => trim((string)($field['label'] ?? 'Field')),
            'type' => $type,
            'side' => $side,
            'x' => dms_normalize_float_range($field['x'] ?? 0, 0.0, 0.0, 85.6),
            'y' => dms_normalize_float_range($field['y'] ?? 0, 0.0, 0.0, 54.1),
            'w' => $baseW,
            'h' => $baseH,
            'maxW' => dms_normalize_float_range($field['maxW'] ?? $defaultMaxW, $defaultMaxW, 1.2, 85.6),
            'maxH' => dms_normalize_float_range($field['maxH'] ?? $defaultMaxH, $defaultMaxH, 1.0, 54.1),
            'z' => dms_normalize_int_range($field['z'] ?? 2, 2, 1, 20),
            'source' => $source,
            'fallbackSource' => trim((string)($field['fallbackSource'] ?? '')),
            'prefix' => trim((string)($field['prefix'] ?? '')),
            'text' => trim((string)($field['text'] ?? '')),
            'align' => $align,
            'fontStyle' => $fontStyle,
            'fontSize' => dms_normalize_float_range($field['fontSize'] ?? 6.0, 6.0, 2.8, 36.0),
            'minFontSize' => dms_normalize_float_range($field['minFontSize'] ?? 4.2, 4.2, 2.4, 24.0),
            'color' => $color,
            'backgroundColor' => $backgroundColor,
            'uppercase' => dms_normalize_bool($field['uppercase'] ?? ($type !== 'cover'), $type !== 'cover'),
            'multiline' => dms_normalize_bool($field['multiline'] ?? false, false),
            'maxLines' => dms_normalize_int_range(
                $field['maxLines'] ?? (dms_normalize_bool($field['multiline'] ?? false, false) ? 2 : 1),
                dms_normalize_bool($field['multiline'] ?? false, false) ? 2 : 1,
                1,
                12
            ),
            'fit' => $fit,
        ];

        if ($normalized['minFontSize'] > $normalized['fontSize']) {
            $normalized['minFontSize'] = $normalized['fontSize'];
        }
        if ((int)$normalized['maxLines'] > 1) {
            $normalized['multiline'] = true;
        }
        $normalized['maxW'] = $freeSize ? 85.6 : max((float)$normalized['w'], (float)$normalized['maxW'], $defaultMaxW);
        $normalized['maxH'] = $freeSize ? 54.1 : max((float)$normalized['h'], (float)$normalized['maxH'], $defaultMaxH);

        if ($normalized['label'] === '') {
            $normalized['label'] = ucfirst(str_replace('_', ' ', $fieldId));
        }

        return $normalized;
    }
}

if (!function_exists('dms_normalize_barangay_id_layout')) {
    function dms_normalize_barangay_id_layout(array $layout): array
    {
        $defaults = dms_barangay_id_default_layout();
        $page = (array)($layout['page'] ?? []);
        $fields = isset($layout['fields']) && is_array($layout['fields']) ? $layout['fields'] : [];
        if ($fields === []) {
            $fields = $defaults['fields'];
        }

        $normalizedFields = [];
        foreach ($fields as $index => $field) {
            if (!is_array($field)) {
                continue;
            }
            $fieldId = strtolower(trim((string)($field['id'] ?? '')));
            $fieldType = strtolower(trim((string)($field['type'] ?? '')));
            if (in_array($fieldType, ['label', 'signatory'], true)
                || trim((string)($field['source'] ?? '')) === 'validityNotice'
                || in_array($fieldId, ['back_validity_notice', 'back_signatory'], true)) {
                continue;
            }
            $normalizedFields[] = dms_normalize_barangay_id_field($field, (int)$index);
        }

        $hasSignature = false;
        foreach ($normalizedFields as $field) {
            if (($field['id'] ?? '') === 'back_signature'
                || (($field['type'] ?? '') === 'image' && ($field['source'] ?? '') === 'punongSignatorySignatureUrl')) {
                $hasSignature = true;
                break;
            }
        }
        if (!$hasSignature) {
            foreach ($defaults['fields'] as $index => $field) {
                if (($field['id'] ?? '') === 'back_signature') {
                    $normalizedFields[] = dms_normalize_barangay_id_field($field, (int)$index);
                    break;
                }
            }
        }

        usort($normalizedFields, static function (array $left, array $right): int {
            $sideCompare = strcmp((string)($left['side'] ?? ''), (string)($right['side'] ?? ''));
            if ($sideCompare !== 0) {
                return $sideCompare;
            }
            $zCompare = ((int)($left['z'] ?? 0)) <=> ((int)($right['z'] ?? 0));
            if ($zCompare !== 0) {
                return $zCompare;
            }
            return strcmp((string)($left['id'] ?? ''), (string)($right['id'] ?? ''));
        });

        return [
            'version' => dms_normalize_int_range($layout['version'] ?? 1, 1, 1, 10),
            'page' => [
                'width_mm' => dms_normalize_float_range($page['width_mm'] ?? $defaults['page']['width_mm'], (float)$defaults['page']['width_mm'], 85.6, 85.6),
                'height_mm' => dms_normalize_float_range($page['height_mm'] ?? $defaults['page']['height_mm'], (float)$defaults['page']['height_mm'], 54.1, 54.1),
            ],
            'fields' => $normalizedFields,
        ];
    }
}

if (!function_exists('dms_normalize_barangay_id_sample_data')) {
    function dms_normalize_barangay_id_sample_data(array $sample): array
    {
        $defaults = dms_barangay_id_default_sample_data();
        $normalized = $defaults;
        foreach ($defaults as $key => $defaultValue) {
            $value = $sample[$key] ?? $defaultValue;
            $normalized[$key] = trim((string)$value);
        }
        if ($normalized['validityNotice'] === '') {
            $normalized['validityNotice'] = 'This ID is valid until ' . ($normalized['validUntil'] !== '' ? $normalized['validUntil'] : '____') . ' except when the holder requests for a new one.';
        }
        return $normalized;
    }
}

if (!function_exists('dms_decode_json_array')) {
    function dms_decode_json_array(?string $json): array
    {
        $json = trim((string)$json);
        if ($json === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('dms_resolve_barangay_id_template_settings')) {
    function dms_resolve_barangay_id_template_settings(mysqli $conn): array
    {
        $stored = dms_fetch_module_template_config_row($conn, 'barangay_id');
        $defaultPaths = dms_barangay_id_default_template_paths();
        $storedFront = trim((string)($stored['template_front_path'] ?? ''));
        $storedBack = trim((string)($stored['template_back_path'] ?? ''));
        $frontResolved = $storedFront !== '' ? $storedFront : $defaultPaths['front'];
        $backResolved = $storedBack !== '' ? $storedBack : $defaultPaths['back'];
        $frontDiskPath = dms_module_asset_public_path_to_disk($frontResolved);
        $backDiskPath = dms_module_asset_public_path_to_disk($backResolved);
        $frontBlob = (string)($stored['template_front_blob'] ?? '');
        $backBlob = (string)($stored['template_back_blob'] ?? '');
        if ($frontDiskPath === '' && $storedFront !== '') {
            $frontDiskPath = dms_restore_template_blob($storedFront, $frontBlob);
        }
        if ($backDiskPath === '' && $storedBack !== '') {
            $backDiskPath = dms_restore_template_blob($storedBack, $backBlob);
        }
        $blobBackfillNeeded = false;
        if ($frontBlob === '' && $storedFront !== '' && $frontDiskPath !== '') {
            $frontBlob = (string)(file_get_contents($frontDiskPath) ?: '');
            $blobBackfillNeeded = $frontBlob !== '';
        }
        if ($backBlob === '' && $storedBack !== '' && $backDiskPath !== '') {
            $backBlob = (string)(file_get_contents($backDiskPath) ?: '');
            $blobBackfillNeeded = $blobBackfillNeeded || $backBlob !== '';
        }
        if ($blobBackfillNeeded) {
            $stmtBlob = $conn->prepare("UPDATE documentmoduleconfigtbl SET template_front_blob = ?, template_back_blob = ? WHERE module_key = 'barangay_id' LIMIT 1");
            if ($stmtBlob) {
                $stmtBlob->bind_param('ss', $frontBlob, $backBlob);
                $stmtBlob->execute();
                $stmtBlob->close();
            }
        }

        $layout = dms_normalize_barangay_id_layout(
            dms_decode_json_array((string)($stored['layout_json'] ?? ''))
        );
        $sampleData = dms_normalize_barangay_id_sample_data(
            dms_decode_json_array((string)($stored['sample_data_json'] ?? ''))
        );

        return [
            'module_key' => 'barangay_id',
            'front_template_path' => $frontResolved,
            'back_template_path' => $backResolved,
            'front_template_custom_path' => $storedFront,
            'back_template_custom_path' => $storedBack,
            'front_template_disk_path' => $frontDiskPath,
            'back_template_disk_path' => $backDiskPath,
            'layout' => $layout,
            'sample_data' => $sampleData,
            'updated_at' => trim((string)($stored['updated_at'] ?? '')),
            'updated_by_user_id' => trim((string)($stored['updated_by_user_id'] ?? '')),
            'template_variant' => ($storedFront !== '' || $storedBack !== '') ? 'custom' : 'empty',
        ];
    }
}

if (!function_exists('dms_upsert_module_template_config')) {
    function dms_upsert_module_template_config(
        mysqli $conn,
        string $moduleKey,
        string $frontTemplatePath,
        string $backTemplatePath,
        string $frontTemplateBlob,
        string $backTemplateBlob,
        array $layout,
        array $sampleData,
        string $updatedByUserId
    ): void {
        dms_ensure_module_template_config_table($conn);

        $layoutJson = dms_json_encode_pretty($layout);
        $sampleJson = dms_json_encode_pretty($sampleData);

        $stmt = $conn->prepare("
            INSERT INTO documentmoduleconfigtbl (
                module_key,
                template_front_path,
                template_back_path,
                template_front_blob,
                template_back_blob,
                layout_json,
                sample_data_json,
                updated_by_user_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                template_front_path = VALUES(template_front_path),
                template_back_path = VALUES(template_back_path),
                template_front_blob = VALUES(template_front_blob),
                template_back_blob = VALUES(template_back_blob),
                layout_json = VALUES(layout_json),
                sample_data_json = VALUES(sample_data_json),
                updated_by_user_id = VALUES(updated_by_user_id),
                updated_at = CURRENT_TIMESTAMP
        ");
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare Barangay ID template settings update.');
        }

        $stmt->bind_param('ssssssss', $moduleKey, $frontTemplatePath, $backTemplatePath, $frontTemplateBlob, $backTemplateBlob, $layoutJson, $sampleJson, $updatedByUserId);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('dms_save_barangay_id_template_settings')) {
    function dms_save_barangay_id_template_settings(mysqli $conn, array $post, array $files, string $updatedByUserId): array
    {
        $before = dms_resolve_barangay_id_template_settings($conn);
        $stored = dms_fetch_module_template_config_row($conn, 'barangay_id');
        $frontStored = trim((string)($stored['template_front_path'] ?? ''));
        $backStored = trim((string)($stored['template_back_path'] ?? ''));
        $frontTemplatePath = $frontStored;
        $backTemplatePath = $backStored;
        $frontTemplateBlob = (string)($stored['template_front_blob'] ?? '');
        $backTemplateBlob = (string)($stored['template_back_blob'] ?? '');
        if ($frontTemplateBlob === '' && $frontStored !== '') {
            $frontDisk = dms_module_asset_public_path_to_disk($frontStored);
            $frontTemplateBlob = $frontDisk !== '' ? (string)(file_get_contents($frontDisk) ?: '') : '';
        }
        if ($backTemplateBlob === '' && $backStored !== '') {
            $backDisk = dms_module_asset_public_path_to_disk($backStored);
            $backTemplateBlob = $backDisk !== '' ? (string)(file_get_contents($backDisk) ?: '') : '';
        }

        if (!empty($post['remove_front_template'])) {
            dms_delete_module_asset_file($frontStored);
            $frontTemplatePath = '';
            $frontTemplateBlob = '';
        }
        if (!empty($post['remove_back_template'])) {
            dms_delete_module_asset_file($backStored);
            $backTemplatePath = '';
            $backTemplateBlob = '';
        }

        if (isset($files['front_template_file']) && is_array($files['front_template_file'])) {
            $uploadErrorCode = (int)($files['front_template_file']['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($uploadErrorCode !== UPLOAD_ERR_NO_FILE) {
                $frontTmpName = trim((string)($files['front_template_file']['tmp_name'] ?? ''));
                $newFrontBlob = $frontTmpName !== '' ? (string)(file_get_contents($frontTmpName) ?: '') : '';
                $newFrontPath = dms_store_uploaded_template_png('barangay_id', 'front', $files['front_template_file']);
                if ($frontStored !== '' && $frontStored !== $newFrontPath) {
                    dms_delete_module_asset_file($frontStored);
                }
                $frontTemplatePath = $newFrontPath;
                $frontTemplateBlob = $newFrontBlob;
            }
        }

        if (isset($files['back_template_file']) && is_array($files['back_template_file'])) {
            $uploadErrorCode = (int)($files['back_template_file']['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($uploadErrorCode !== UPLOAD_ERR_NO_FILE) {
                $backTmpName = trim((string)($files['back_template_file']['tmp_name'] ?? ''));
                $newBackBlob = $backTmpName !== '' ? (string)(file_get_contents($backTmpName) ?: '') : '';
                $newBackPath = dms_store_uploaded_template_png('barangay_id', 'back', $files['back_template_file']);
                if ($backStored !== '' && $backStored !== $newBackPath) {
                    dms_delete_module_asset_file($backStored);
                }
                $backTemplatePath = $newBackPath;
                $backTemplateBlob = $newBackBlob;
            }
        }

        $layoutInput = dms_decode_json_array((string)($post['barangay_id_layout_json'] ?? ''));
        $layout = dms_normalize_barangay_id_layout($layoutInput);
        if (empty($layout['fields'])) {
            throw new RuntimeException('Barangay ID layout must contain at least one field.');
        }

        $sampleInput = dms_decode_json_array((string)($post['barangay_id_sample_json'] ?? ''));
        $sampleData = dms_normalize_barangay_id_sample_data($sampleInput);

        dms_upsert_module_template_config(
            $conn,
            'barangay_id',
            $frontTemplatePath,
            $backTemplatePath,
            $frontTemplateBlob,
            $backTemplateBlob,
            $layout,
            $sampleData,
            $updatedByUserId
        );

        return [
            'before' => $before,
            'after' => dms_resolve_barangay_id_template_settings($conn),
        ];
    }
}
