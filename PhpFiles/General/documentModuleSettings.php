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
                    'secretary' => [
                        'label' => 'Barangay Secretary',
                        'source' => 'seat_secretary',
                        'signature_help' => 'Shown on the issued-by block and witness block when the template uses it.',
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
                    'secretary' => [
                        'label' => 'Barangay Secretary',
                        'source' => 'seat_secretary',
                        'signature_help' => 'Shown on the issued-by block for monitoring documents.',
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
                'description' => 'Manage the signatory asset used on the generated Barangay ID card.',
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
