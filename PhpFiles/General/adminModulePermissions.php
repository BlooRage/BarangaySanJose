<?php
declare(strict_types=1);

require_once __DIR__ . '/uniqueIDGenerate.php';

/**
 * Shared admin-module permission catalog and helpers.
 *
 * The sidebar, officials access editor, and admin-page guard should all read
 * from the same catalog so the visible labels and the enforced permissions
 * stay aligned.
 */

if (!function_exists('amp_get_permission_catalog')) {
    function amp_get_permission_catalog(): array
    {
        static $catalog = null;
        if ($catalog !== null) {
            return $catalog;
        }

        $catalog = [
            [
                'section' => 'Home',
                'items' => [
                    [
                        'key' => 'dashboard',
                        'label' => 'Dashboard',
                        'path' => 'Admin-End/AdminDashboard.php',
                    ],
                ],
            ],
            [
                'section' => 'Office of the Barangay',
                'items' => [
                    [
                        'key' => 'appointments',
                        'label' => 'Appointments',
                        'path' => 'Admin-End/Appointments/AppointmentTracker.php?tool=tracker',
                    ],
                ],
            ],
            [
                'section' => 'Resident Management',
                'items' => [
                    [
                        'key' => 'resident_profiling',
                        'label' => 'Resident Profiling',
                        'children' => [
                            [
                                'key' => 'resident_masterlist',
                                'label' => 'Resident Tracker',
                                'path' => 'Admin-End/ResidentTracker.php',
                            ],
                            [
                                'key' => 'resident_edit_requests',
                                'label' => 'Edit Requests',
                                'path' => 'Admin-End/EditRequests.php',
                            ],
                            [
                                'key' => 'resident_archive',
                                'label' => 'Resident Archive',
                                'path' => 'Admin-End/ResidentArchive.php',
                            ],
                            [
                                'key' => 'resident_sector_membership_verification',
                                'label' => 'Sector Membership Verification',
                                'path' => 'Admin-End/SectorMembershipVerification.php',
                            ],
                        ],
                    ],
                    [
                        'key' => 'household_profiling',
                        'label' => 'Household Profiling',
                        'children' => [
                            [
                                'key' => 'household_profiling_main',
                                'label' => 'Household Profiling',
                                'path' => 'Admin-End/HouseholdProfiling.php',
                            ],
                            [
                                'key' => 'head_of_family_verification',
                                'label' => 'Head of the Family Verification',
                                'path' => 'Admin-End/HeadOfTheFamilyVerification.php',
                            ],
                            [
                                'key' => 'household_member_verification',
                                'label' => 'Household Member Verification',
                                'path' => 'Admin-End/HouseholdMemberVerification.php',
                            ],
                        ],
                    ],
                    [
                        'key' => 'area_statistics',
                        'label' => 'Statistics',
                        'children' => [
                            [
                                'key' => 'area_statistics_summary',
                                'label' => 'Area Statistics',
                                'path' => 'Admin-End/AreaManagement/AreaStatistics.php?tab=summary',
                            ],
                            [
                                'key' => 'area_profile_area_01',
                                'label' => 'Area 01',
                                'path' => 'Admin-End/AreaManagement/AreaProfile.php?area=Area%2001',
                            ],
                            [
                                'key' => 'area_profile_area_1a',
                                'label' => 'Area 1A',
                                'path' => 'Admin-End/AreaManagement/AreaProfile.php?area=Area%201A',
                            ],
                            [
                                'key' => 'area_profile_area_02',
                                'label' => 'Area 02',
                                'path' => 'Admin-End/AreaManagement/AreaProfile.php?area=Area%2002',
                            ],
                            [
                                'key' => 'area_profile_area_03',
                                'label' => 'Area 03',
                                'path' => 'Admin-End/AreaManagement/AreaProfile.php?area=Area%2003',
                            ],
                            [
                                'key' => 'area_profile_area_04',
                                'label' => 'Area 04',
                                'path' => 'Admin-End/AreaManagement/AreaProfile.php?area=Area%2004',
                            ],
                            [
                                'key' => 'area_profile_area_05',
                                'label' => 'Area 05',
                                'path' => 'Admin-End/AreaManagement/AreaProfile.php?area=Area%2005',
                            ],
                            [
                                'key' => 'area_profile_area_06',
                                'label' => 'Area 06',
                                'path' => 'Admin-End/AreaManagement/AreaProfile.php?area=Area%2006',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'section' => 'Barangay Issuance',
                'items' => [
                    [
                        'key' => 'certificate_issuance',
                        'label' => 'Certificate Issuance',
                        'path' => 'Admin-End/Certificates/CertificateTracker.php?filter_document=__certificates__',
                    ],
                    [
                        'key' => 'id_issuance',
                        'label' => 'ID Issuance',
                        'children' => [
                            [
                                'key' => 'id_issuance_tracker',
                                'label' => 'Tracker',
                                'path' => 'Admin-End/Certificates/CertificateTracker.php?entry=id_issuance',
                            ],
                            [
                                'key' => 'id_issuance_manual',
                                'label' => 'Manual Issuance',
                                'path' => 'Admin-End/Certificates/CertificateTracker.php?tab=manual&document=barangay_id',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'section' => 'Barangay Monitoring',
                'items' => [
                    [
                        'key' => 'clearance_issuance',
                        'label' => 'Clearance Issuance',
                        'path' => 'Admin-End/Certificates/CertificateTracker.php?filter_document=__clearances__',
                    ],
                    [
                        'key' => 'business_monitoring',
                        'label' => 'Business Monitoring',
                        'path' => 'Admin-End/BusinessMonitoring.php',
                    ],
                ],
            ],
            [
                'section' => 'Barangay Treasury',
                'items' => [
                    [
                        'key' => 'finance_transactions',
                        'label' => 'Finance Transactions',
                        'children' => [
                            [
                                'key' => 'finance_payment_tracker',
                                'label' => 'Payment Tracker',
                                'path' => 'Admin-End/Certificates/FinancePayments.php',
                            ],
                            [
                                'key' => 'finance_create_transaction',
                                'label' => 'Create Transaction',
                                'path' => 'Admin-End/Certificates/FinancePayments.php?section=create',
                            ],
                            [
                                'key' => 'finance_fee_management',
                                'label' => 'Fee Management',
                                'path' => 'Admin-End/Certificates/FinancePayments.php?section=fees',
                            ],
                            [
                                'key' => 'finance_payment_settings',
                                'label' => 'Finance Settings',
                                'path' => 'Admin-End/Certificates/FinancePayments.php?section=settings',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'section' => 'Peace and Order',
                'items' => [
                    [
                        'key' => 'blotter_log_new_incident',
                        'label' => 'Blotter - Log New Incident',
                        'path' => 'Admin-End/Blotter/BlotterForm.php',
                    ],
                    [
                        'key' => 'blotter_tools',
                        'label' => 'e-Blotter Tools',
                        'children' => [
                            [
                                'key' => 'blotter_tracker',
                                'label' => 'Tracker',
                                'path' => 'Admin-End/Blotter/BlotterTracker.php',
                            ],
                            [
                                'key' => 'blotter_review_queue',
                                'label' => 'Review Queue',
                                'path' => 'Admin-End/Blotter/ReviewQueue.php',
                            ],
                        ],
                    ],
                    [
                        'key' => 'complaint_log_new_incident',
                        'label' => 'Complaint - Log New Incident',
                        'path' => 'Admin-End/Complaints/ComplaintForm.php',
                    ],
                    [
                        'key' => 'complaint_tools',
                        'label' => 'Complaint Tools',
                        'children' => [
                            [
                                'key' => 'complaint_tracker',
                                'label' => 'Tracker',
                                'path' => 'Admin-End/Complaints/ComplaintTracker.php',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'section' => 'General Modules',
                'items' => [
                    [
                        'key' => 'news_management',
                        'label' => 'News',
                        'path' => 'Admin-End/Contents/Contents.php?tool=tracker&type_filter=news',
                    ],
                    [
                        'key' => 'announcements',
                        'label' => 'Announcements',
                        'children' => [
                            [
                                'key' => 'announcements_page',
                                'label' => 'Page Announcement',
                                'path' => 'Admin-End/Contents/CreateContent.php?type=page',
                            ],
                            [
                                'key' => 'announcements_delivery',
                                'label' => 'SMS and Email',
                                'path' => 'Admin-End/Contents/CreateContent.php?type=delivery',
                            ],
                            [
                                'key' => 'announcements_faq',
                                'label' => 'FAQs',
                                'path' => 'Admin-End/Contents/CreateContent.php?type=faq',
                            ],
                            [
                                'key' => 'announcements_tracker',
                                'label' => 'Tracker',
                                'path' => 'Admin-End/Contents/Contents.php?tool=tracker',
                            ],
                        ],
                    ],
                    [
                        'key' => 'reports',
                        'label' => 'Reports',
                        'children' => [
                            [
                                'key' => 'reports_certificate_issuance',
                                'label' => 'Certificate Issuance',
                                'path' => 'Admin-End/Reports/Reports.php?module=certificate_issuance',
                            ],
                            [
                                'key' => 'reports_clearance_issuance',
                                'label' => 'Clearance Issuance',
                                'path' => 'Admin-End/Reports/Reports.php?module=clearance_issuance',
                            ],
                            [
                                'key' => 'reports_financial',
                                'label' => 'Financial',
                                'path' => 'Admin-End/Reports/Reports.php?module=financial',
                            ],
                            [
                                'key' => 'reports_residents',
                                'label' => 'Residents',
                                'path' => 'Admin-End/Reports/Reports.php?module=residents',
                            ],
                            [
                                'key' => 'reports_blotter',
                                'label' => 'Blotter',
                                'path' => 'Admin-End/Reports/Reports.php?module=blotter',
                            ],
                            [
                                'key' => 'reports_complaints',
                                'label' => 'Complaints',
                                'path' => 'Admin-End/Reports/Reports.php?module=complaints',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'section' => 'Admin Management',
                'items' => [
                    [
                        'key' => 'user_management',
                        'label' => 'User Management',
                        'admin_only' => true,
                        'children' => [
                            [
                                'key' => 'user_masterlist',
                                'label' => 'User Masterlist',
                                'path' => 'Admin-End/UserMasterlist.php',
                                'admin_only' => true,
                            ],
                            [
                                'key' => 'user_archive',
                                'label' => 'User Archive',
                                'path' => 'Admin-End/UserArchive.php',
                                'admin_only' => true,
                            ],
                        ],
                    ],
                    [
                        'key' => 'admin_management',
                        'label' => 'Admin Management',
                        'path' => 'Admin-End/AdminManagement.php',
                        'admin_only' => true,
                    ],
                    [
                        'key' => 'personnel_management',
                        'label' => 'Personnel Management',
                        'admin_only' => true,
                        'children' => [
                            [
                                'key' => 'officials_management',
                                'label' => 'Personnel Tracker',
                                'path' => 'Admin-End/PersonnelTracker.php',
                                'admin_only' => true,
                            ],
                            [
                                'key' => 'personnel_invite',
                                'label' => 'Account Invite',
                                'path' => 'Admin-End/OfficialInvites.php',
                                'admin_only' => true,
                            ],
                        ],
                    ],
                    [
                        'key' => 'audit_logs',
                        'label' => 'Audit Logs',
                        'path' => 'Admin-End/AuditLogs.php',
                        'admin_only' => true,
                    ],
                    [
                        'key' => 'website_settings',
                        'label' => 'Website Settings',
                        'path' => 'Admin-End/WebsiteSettings.php',
                        'admin_only' => true,
                    ],
                ],
            ],
            [
                'section' => 'Barangay Officials & Access',
                'items' => [
                    [
                        'key' => 'official_records_management',
                        'label' => 'Official Records',
                        'path' => 'Admin-End/OfficialsManagement.php',
                        'admin_only' => true,
                    ],
                    [
                        'key' => 'official_transition',
                        'label' => 'Council Seats & Accounts',
                        'path' => 'Admin-End/OfficialTransitions.php',
                        'admin_only' => true,
                    ],
                ],
            ],
        ];

        return $catalog;
    }
}

if (!function_exists('amp_walk_catalog_items')) {
    function amp_walk_catalog_items(callable $callback): void
    {
        $catalog = amp_get_permission_catalog();
        foreach ($catalog as $section) {
            foreach (($section['items'] ?? []) as $item) {
                $callback($item, $section);
                foreach (($item['children'] ?? []) as $child) {
                    $callback($child, $section, $item);
                }
            }
        }
    }
}

if (!function_exists('amp_get_leaf_permissions')) {
    function amp_get_leaf_permissions(): array
    {
        static $leafPermissions = null;
        if ($leafPermissions !== null) {
            return $leafPermissions;
        }

        $leafPermissions = [];
        amp_walk_catalog_items(static function (array $item, array $section, ?array $parent = null) use (&$leafPermissions): void {
            if (!empty($item['children'])) {
                return;
            }

            $key = trim((string)($item['key'] ?? ''));
            if ($key === '') {
                return;
            }

            $leafPermissions[$key] = [
                'key' => $key,
                'label' => (string)($item['label'] ?? $key),
                'path' => (string)($item['path'] ?? ''),
                'admin_only' => !empty($item['admin_only']) || !empty($parent['admin_only']),
                'section' => (string)($section['section'] ?? ''),
                'parent_key' => (string)($parent['key'] ?? ''),
                'parent_label' => (string)($parent['label'] ?? ''),
            ];
        });

        return $leafPermissions;
    }
}

if (!function_exists('amp_get_permission_meta')) {
    function amp_get_permission_meta(string $permissionKey): ?array
    {
        $all = amp_get_leaf_permissions();
        return $all[$permissionKey] ?? null;
    }
}

if (!function_exists('amp_is_admin_only_permission')) {
    function amp_is_admin_only_permission(string $permissionKey): bool
    {
        $meta = amp_get_permission_meta($permissionKey);
        return !empty($meta['admin_only']);
    }
}

if (!function_exists('amp_get_all_leaf_permission_keys')) {
    function amp_get_all_leaf_permission_keys(): array
    {
        return array_keys(amp_get_leaf_permissions());
    }
}

if (!function_exists('amp_get_default_admin_permission_keys')) {
    function amp_get_default_admin_permission_keys(): array
    {
        static $keys = null;
        if ($keys !== null) {
            return $keys;
        }

        $keys = [];
        foreach (amp_get_leaf_permissions() as $key => $meta) {
            if (empty($meta['admin_only'])) {
                $keys[] = $key;
            }
        }
        return $keys;
    }
}

if (!function_exists('amp_get_it_superadmin_locked_permission_keys')) {
    function amp_get_it_superadmin_locked_permission_keys(): array
    {
        return [
            'dashboard',
            'user_masterlist',
            'user_archive',
            'admin_management',
            'officials_management',
            'official_records_management',
            'personnel_invite',
            'official_transition',
            'audit_logs',
            'website_settings',
        ];
    }
}

if (!function_exists('amp_storage_role_to_display_role')) {
    function amp_storage_role_to_display_role(string $roleAccess): string
    {
        $role = strtolower(trim($roleAccess));
        if ($role === 'superadmin') {
            return 'SuperAdmin';
        }
        return 'Admin';
    }
}

if (!function_exists('amp_normalize_storage_role')) {
    function amp_normalize_storage_role(string $roleAccess): string
    {
        $role = strtolower(trim($roleAccess));
        if ($role === 'superadmin') {
            return 'SuperAdmin';
        }
        if ($role === 'personnel' || $role === 'personnels') {
            return 'Personnel';
        }
        return 'Official';
    }
}

if (!function_exists('amp_storage_role_for_admin_display')) {
    function amp_storage_role_for_admin_display(string $positionAccess, string $currentRoleAccess = ''): string
    {
        $normalizedCurrent = amp_normalize_storage_role($currentRoleAccess);
        if ($normalizedCurrent === 'Personnel') {
            return 'Personnel';
        }

        $position = strtolower(trim($positionAccess));
        $personnelPositions = [
            'department public assistance desk',
            'department secretary',
            'department oic (officer in charge)',
            'barangay police',
            'desk officer',
            'area oic',
            'barangay treasurer',
        ];

        return in_array($position, $personnelPositions, true) ? 'Personnel' : 'Official';
    }
}

if (!function_exists('amp_get_personnel_position_labels')) {
    function amp_get_personnel_position_labels(): array
    {
        return [
            'Department Public Assistance Desk',
            'Department Secretary',
            'Department OIC (Officer In Charge)',
            'Barangay Police',
            'Desk Officer',
            'Area OIC',
            'Barangay Treasurer',
        ];
    }
}

if (!function_exists('amp_is_personnel_position')) {
    function amp_is_personnel_position(string $positionAccess): bool
    {
        $normalized = strtolower(trim($positionAccess));
        if ($normalized === '') {
            return false;
        }

        foreach (amp_get_personnel_position_labels() as $label) {
            if ($normalized === strtolower(trim($label))) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('amp_row_uses_personnel_role_profile')) {
    function amp_row_uses_personnel_role_profile(array $row): bool
    {
        $role = amp_normalize_storage_role((string)($row['account_role_access'] ?? $row['info_role_access'] ?? ''));
        if ($role === 'SuperAdmin') {
            return false;
        }

        $positionAccess = trim((string)($row['position_access'] ?? ''));
        return $role === 'Personnel' || amp_is_personnel_position($positionAccess);
    }
}

if (!function_exists('amp_normalize_profile_scope_value')) {
    function amp_normalize_profile_scope_value(string $value): string
    {
        $value = str_replace(["\r", "\n", "\t"], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        return strtolower(trim($value));
    }
}

if (!function_exists('amp_get_personnel_role_profile_scope')) {
    function amp_get_personnel_role_profile_scope(string $department, string $positionAccess): array
    {
        $departmentLabel = trim((string)(preg_replace('/\s+/', ' ', str_replace(["\r", "\n", "\t"], ' ', $department)) ?? $department));
        $positionLabel = trim((string)(preg_replace('/\s+/', ' ', str_replace(["\r", "\n", "\t"], ' ', $positionAccess)) ?? $positionAccess));

        return [
            'department_key' => amp_normalize_profile_scope_value($departmentLabel),
            'position_key' => amp_normalize_profile_scope_value($positionLabel),
            'department_label' => $departmentLabel,
            'position_label' => $positionLabel,
        ];
    }
}

if (!function_exists('amp_permission_keys_have_any')) {
    function amp_permission_keys_have_any(array $allowedKeys, array $candidateKeys): bool
    {
        foreach ($candidateKeys as $key) {
            if (isset($allowedKeys[$key]) && $allowedKeys[$key] === true) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('amp_permission_key_allowed')) {
    function amp_permission_key_allowed(array $allowedKeys, string $permissionKey): bool
    {
        if ($permissionKey === 'finance_payment_settings' && !empty($allowedKeys['finance_fee_management'])) {
            return true;
        }
        return isset($allowedKeys[$permissionKey]) && $allowedKeys[$permissionKey] === true;
    }
}

if (!function_exists('amp_column_exists')) {
    function amp_column_exists(mysqli $conn, string $table, string $column): bool
    {
        $tableEsc = $conn->real_escape_string($table);
        $columnEsc = $conn->real_escape_string($column);
        $res = $conn->query("SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'");
        return $res instanceof mysqli_result && $res->num_rows > 0;
    }
}

if (!function_exists('amp_session_cache_get')) {
    function amp_session_cache_get(string $key, int $ttlSeconds)
    {
        if ($key === '' || $ttlSeconds <= 0) {
            return null;
        }

        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            return null;
        }

        $bucket = $_SESSION['amp_runtime_cache'] ?? null;
        if (!is_array($bucket) || !isset($bucket[$key]) || !is_array($bucket[$key])) {
            return null;
        }

        $entry = $bucket[$key];
        $createdAt = (int)($entry['created_at'] ?? 0);
        if ($createdAt <= 0 || (time() - $createdAt) > $ttlSeconds) {
            unset($_SESSION['amp_runtime_cache'][$key]);
            return null;
        }

        return $entry['value'] ?? null;
    }
}

if (!function_exists('amp_session_cache_put')) {
    function amp_session_cache_put(string $key, $value): void
    {
        if ($key === '') {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        if (!isset($_SESSION['amp_runtime_cache']) || !is_array($_SESSION['amp_runtime_cache'])) {
            $_SESSION['amp_runtime_cache'] = [];
        }

        $_SESSION['amp_runtime_cache'][$key] = [
            'created_at' => time(),
            'value' => $value,
        ];
    }
}

if (!function_exists('amp_shared_cache_path')) {
    function amp_shared_cache_path(string $key): string
    {
        $safeKey = preg_replace('/[^a-zA-Z0-9_.-]+/', '_', $key) ?? 'cache';
        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'barangaysanjose_' . $safeKey . '.cache';
    }
}

if (!function_exists('amp_shared_cache_get')) {
    function amp_shared_cache_get(string $key, int $ttlSeconds)
    {
        if ($key === '' || $ttlSeconds <= 0) {
            return null;
        }

        $path = amp_shared_cache_path($key);
        if (!is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            return null;
        }

        $createdAt = (int)($payload['created_at'] ?? 0);
        if ($createdAt <= 0 || (time() - $createdAt) > $ttlSeconds) {
            return null;
        }

        return $payload['value'] ?? null;
    }
}

if (!function_exists('amp_shared_cache_put')) {
    function amp_shared_cache_put(string $key, $value): void
    {
        if ($key === '') {
            return;
        }

        $payload = json_encode([
            'created_at' => time(),
            'value' => $value,
        ]);
        if (!is_string($payload) || $payload === '') {
            return;
        }

        @file_put_contents(amp_shared_cache_path($key), $payload, LOCK_EX);
    }
}

if (!function_exists('amp_ensure_permission_storage')) {
    function amp_ensure_permission_storage(mysqli $conn): void
    {
        static $done = false;
        if ($done) {
            return;
        }

        $sessionCacheKey = 'permission_storage_verified_v1';
        if (amp_session_cache_get($sessionCacheKey, 900) === true) {
            $done = true;
            return;
        }

        $sharedCacheKey = 'permission_storage_verified_v1';
        if (amp_shared_cache_get($sharedCacheKey, 86400) === true) {
            amp_session_cache_put($sessionCacheKey, true);
            $done = true;
            return;
        }

        $done = true;

        if (!amp_column_exists($conn, 'officialinformationtbl', 'term_end')) {
            $conn->query("ALTER TABLE officialinformationtbl ADD COLUMN term_end DATE DEFAULT NULL");
        }

        $conn->query("
            CREATE TABLE IF NOT EXISTS officialmodulepermissionstbl (
                permission_id INT NOT NULL,
                official_id VARCHAR(20) NOT NULL,
                user_id VARCHAR(20) DEFAULT NULL,
                permission_key VARCHAR(120) NOT NULL,
                is_allowed TINYINT(1) NOT NULL DEFAULT 1,
                granted_by_user_id VARCHAR(20) DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (permission_id),
                UNIQUE KEY uniq_official_permission (official_id, permission_key),
                KEY idx_permission_user (user_id),
                KEY idx_permission_key (permission_key),
                KEY idx_permission_allowed (is_allowed)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $conn->query("
            CREATE TABLE IF NOT EXISTS officialaccessprofiletbl (
                access_profile_id INT NOT NULL,
                official_id VARCHAR(20) NOT NULL,
                user_id VARCHAR(20) DEFAULT NULL,
                permissions_initialized TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (access_profile_id),
                UNIQUE KEY uniq_access_profile_official (official_id),
                UNIQUE KEY uniq_access_profile_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $conn->query("
            CREATE TABLE IF NOT EXISTS officialseatmodulepermissionstbl (
                seat_permission_id INT NOT NULL,
                council_id INT NOT NULL,
                permission_key VARCHAR(120) NOT NULL,
                is_allowed TINYINT(1) NOT NULL DEFAULT 1,
                granted_by_user_id VARCHAR(20) DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (seat_permission_id),
                UNIQUE KEY uniq_seat_permission (council_id, permission_key),
                KEY idx_seat_permission_key (permission_key),
                KEY idx_seat_permission_allowed (is_allowed)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $conn->query("
            CREATE TABLE IF NOT EXISTS officialseataccessprofiletbl (
                seat_access_profile_id INT NOT NULL,
                council_id INT NOT NULL,
                permissions_initialized TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (seat_access_profile_id),
                UNIQUE KEY uniq_seat_access_profile_council (council_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $conn->query("
            CREATE TABLE IF NOT EXISTS officialaccessrolepermissiontbl (
                role_permission_id INT NOT NULL,
                department_key VARCHAR(160) NOT NULL DEFAULT '',
                position_key VARCHAR(160) NOT NULL DEFAULT '',
                department_label VARCHAR(160) DEFAULT NULL,
                position_label VARCHAR(160) DEFAULT NULL,
                permission_key VARCHAR(120) NOT NULL,
                is_allowed TINYINT(1) NOT NULL DEFAULT 1,
                granted_by_user_id VARCHAR(20) DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (role_permission_id),
                UNIQUE KEY uniq_access_role_permission (department_key, position_key, permission_key),
                KEY idx_access_role_scope (department_key, position_key),
                KEY idx_access_role_permission_key (permission_key),
                KEY idx_access_role_allowed (is_allowed)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $conn->query("
            CREATE TABLE IF NOT EXISTS officialaccessroleprofiletbl (
                role_access_profile_id INT NOT NULL,
                department_key VARCHAR(160) NOT NULL DEFAULT '',
                position_key VARCHAR(160) NOT NULL DEFAULT '',
                department_label VARCHAR(160) DEFAULT NULL,
                position_label VARCHAR(160) DEFAULT NULL,
                area_assignee_access VARCHAR(50) DEFAULT NULL,
                area_coverage_access TEXT DEFAULT NULL,
                permissions_initialized TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (role_access_profile_id),
                UNIQUE KEY uniq_access_role_profile (department_key, position_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $conn->query("
            CREATE TABLE IF NOT EXISTS personnelrolemodulepermissionstbl (
                role_permission_id INT NOT NULL,
                department_key VARCHAR(160) NOT NULL DEFAULT '',
                position_key VARCHAR(160) NOT NULL DEFAULT '',
                department_label VARCHAR(160) DEFAULT NULL,
                position_label VARCHAR(160) DEFAULT NULL,
                permission_key VARCHAR(120) NOT NULL,
                is_allowed TINYINT(1) NOT NULL DEFAULT 1,
                granted_by_user_id VARCHAR(20) DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (role_permission_id),
                UNIQUE KEY uniq_personnel_role_permission (department_key, position_key, permission_key),
                KEY idx_personnel_role_scope (department_key, position_key),
                KEY idx_personnel_role_permission_key (permission_key),
                KEY idx_personnel_role_allowed (is_allowed)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $conn->query("
            CREATE TABLE IF NOT EXISTS personnelroleaccessprofiletbl (
                role_access_profile_id INT NOT NULL,
                department_key VARCHAR(160) NOT NULL DEFAULT '',
                position_key VARCHAR(160) NOT NULL DEFAULT '',
                department_label VARCHAR(160) DEFAULT NULL,
                position_label VARCHAR(160) DEFAULT NULL,
                permissions_initialized TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (role_access_profile_id),
                UNIQUE KEY uniq_personnel_role_profile (department_key, position_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        idg_ensure_numeric_generated_key($conn, 'officialmodulepermissionstbl', 'permission_id', 'INT NOT NULL');
        idg_ensure_numeric_generated_key($conn, 'officialaccessprofiletbl', 'access_profile_id', 'INT NOT NULL');
        idg_ensure_numeric_generated_key($conn, 'officialseatmodulepermissionstbl', 'seat_permission_id', 'INT NOT NULL');
        idg_ensure_numeric_generated_key($conn, 'officialseataccessprofiletbl', 'seat_access_profile_id', 'INT NOT NULL');
        idg_ensure_numeric_generated_key($conn, 'officialaccessrolepermissiontbl', 'role_permission_id', 'INT NOT NULL');
        idg_ensure_numeric_generated_key($conn, 'officialaccessroleprofiletbl', 'role_access_profile_id', 'INT NOT NULL');
        idg_ensure_numeric_generated_key($conn, 'personnelrolemodulepermissionstbl', 'role_permission_id', 'INT NOT NULL');
        idg_ensure_numeric_generated_key($conn, 'personnelroleaccessprofiletbl', 'role_access_profile_id', 'INT NOT NULL');

        amp_session_cache_put($sessionCacheKey, true);
        amp_shared_cache_put($sharedCacheKey, true);
    }
}

if (!function_exists('amp_get_status_id_by_names')) {
    function amp_get_status_id_by_names(mysqli $conn, string $statusType, array $preferredNames): ?int
    {
        if (!$preferredNames) {
            return null;
        }

        $stmt = $conn->prepare("SELECT status_id, status_name FROM statuslookuptbl WHERE status_type = ?");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $statusType);
        $stmt->execute();
        $res = $stmt->get_result();
        $map = [];
        while ($row = $res->fetch_assoc()) {
            $name = strtolower(trim((string)($row['status_name'] ?? '')));
            if ($name !== '') {
                $map[$name] = (int)($row['status_id'] ?? 0);
            }
        }
        $stmt->close();

        foreach ($preferredNames as $name) {
            $lookup = strtolower(trim((string)$name));
            if ($lookup !== '' && isset($map[$lookup]) && $map[$lookup] > 0) {
                return $map[$lookup];
            }
        }

        return null;
    }
}

if (!function_exists('amp_get_official_account_by_user_id')) {
    function amp_get_official_account_by_user_id(mysqli $conn, string $userId): ?array
    {
        amp_ensure_permission_storage($conn);

        $userId = trim($userId);
        if ($userId === '') {
            return null;
        }

        $sessionCacheKey = 'official_account:' . md5($userId);
        $cached = amp_session_cache_get($sessionCacheKey, 1800);
        if (is_array($cached)) {
            return $cached;
        }

        $sharedCached = amp_shared_cache_get($sessionCacheKey, 1800);
        if (is_array($sharedCached)) {
            amp_session_cache_put($sessionCacheKey, $sharedCached);
            return $sharedCached;
        }

        $hasPositionAccess = amp_column_exists($conn, 'officialinformationtbl', 'position_access');
        $positionField = $hasPositionAccess ? 'oi.position_access' : 'oi.role_access';
        $hasAreaNumber = amp_column_exists($conn, 'officialinformationtbl', 'area_number');
        $areaField = $hasAreaNumber ? 'oi.area_number' : 'NULL AS area_number';

        $stmt = $conn->prepare("
            SELECT
                oi.official_id,
                oi.user_id,
                oi.role_access AS info_role_access,
                {$positionField} AS position_access,
                oi.department,
                {$areaField},
                oi.term_end,
                ua.role_access AS account_role_access,
                ua.status_id_account,
                COALESCE(sa.status_name, '') AS account_status
            FROM officialinformationtbl oi
            INNER JOIN useraccountstbl ua
                ON ua.user_id COLLATE utf8mb4_general_ci = oi.user_id COLLATE utf8mb4_general_ci
            LEFT JOIN statuslookuptbl sa ON sa.status_id = ua.status_id_account
            WHERE oi.user_id COLLATE utf8mb4_general_ci = ?
            LIMIT 1
        ");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $resolved = $row ?: null;
        if (is_array($resolved)) {
            amp_session_cache_put($sessionCacheKey, $resolved);
            amp_shared_cache_put($sessionCacheKey, $resolved);
        }
        return $resolved;
    }
}

if (!function_exists('amp_has_saved_access_profile')) {
    function amp_has_saved_access_profile(mysqli $conn, string $officialId): bool
    {
        amp_ensure_permission_storage($conn);
        $officialId = trim($officialId);
        if ($officialId === '') {
            return false;
        }

        $stmt = $conn->prepare("
            SELECT 1
            FROM officialaccessprofiletbl
            WHERE official_id = ?
              AND permissions_initialized = 1
            LIMIT 1
        ");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('s', $officialId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_row();
        $stmt->close();

        return $row !== null;
    }
}

if (!function_exists('amp_replace_official_module_permissions')) {
    function amp_replace_official_module_permissions(mysqli $conn, string $officialId, string $userId, array $permissionKeys, string $grantedByUserId): void
    {
        amp_ensure_permission_storage($conn);

        $officialId = trim($officialId);
        if ($officialId === '') {
            throw new RuntimeException('Official ID is required to save module permissions.');
        }

        $deleteStmt = $conn->prepare("DELETE FROM officialmodulepermissionstbl WHERE official_id = ?");
        if ($deleteStmt) {
            $deleteStmt->bind_param('s', $officialId);
            $deleteStmt->execute();
            $deleteStmt->close();
        }

        if (!$permissionKeys) {
            return;
        }

        foreach ($permissionKeys as $permissionKey) {
            $permissionKey = trim((string)$permissionKey);
            if ($permissionKey === '') {
                continue;
            }
            $permissionId = GenerateTenDigitMetaID($conn, 'officialmodulepermissionstbl', 'permission_id');
            if ($permissionId === false) {
                throw new RuntimeException('Failed to generate official permission ID.');
            }
            $insertStmt = $conn->prepare("
                INSERT INTO officialmodulepermissionstbl
                    (permission_id, official_id, user_id, permission_key, is_allowed, granted_by_user_id)
                VALUES
                    (?, ?, NULLIF(?, ''), ?, 1, NULLIF(?, ''))
            ");
            if (!$insertStmt) {
                throw new RuntimeException('Failed to save official module permissions.');
            }
            $permissionIdInt = (int)$permissionId;
            $insertStmt->bind_param('issss', $permissionIdInt, $officialId, $userId, $permissionKey, $grantedByUserId);
            $insertStmt->execute();
            $insertStmt->close();
        }
    }
}

if (!function_exists('amp_upsert_official_access_profile')) {
    function amp_upsert_official_access_profile(mysqli $conn, string $officialId, string $userId): void
    {
        amp_ensure_permission_storage($conn);

        $officialId = trim($officialId);
        if ($officialId === '') {
            throw new RuntimeException('Official ID is required to save access profile metadata.');
        }

        $stmt = $conn->prepare("
            INSERT INTO officialaccessprofiletbl (access_profile_id, official_id, user_id, permissions_initialized)
            VALUES (?, ?, NULLIF(?, ''), 1)
            ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                permissions_initialized = 1,
                updated_at = CURRENT_TIMESTAMP
        ");
        if (!$stmt) {
            throw new RuntimeException('Failed to save official access profile metadata.');
        }
        $accessProfileId = GenerateTenDigitMetaID($conn, 'officialaccessprofiletbl', 'access_profile_id');
        if ($accessProfileId === false) {
            throw new RuntimeException('Failed to generate official access profile ID.');
        }
        $accessProfileIdInt = (int)$accessProfileId;
        $stmt->bind_param('iss', $accessProfileIdInt, $officialId, $userId);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('amp_has_saved_seat_access_profile')) {
    function amp_has_saved_seat_access_profile(mysqli $conn, int $councilId): bool
    {
        amp_ensure_permission_storage($conn);

        if ($councilId <= 0) {
            return false;
        }

        $stmt = $conn->prepare("
            SELECT 1
            FROM officialseataccessprofiletbl
            WHERE council_id = ?
              AND permissions_initialized = 1
            LIMIT 1
        ");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $councilId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_row();
        $stmt->close();

        return $row !== null;
    }
}

if (!function_exists('amp_replace_seat_module_permissions')) {
    function amp_replace_seat_module_permissions(mysqli $conn, int $councilId, array $permissionKeys, string $grantedByUserId): void
    {
        amp_ensure_permission_storage($conn);

        if ($councilId <= 0) {
            throw new RuntimeException('Council seat is required to save seat module permissions.');
        }

        $deleteStmt = $conn->prepare("DELETE FROM officialseatmodulepermissionstbl WHERE council_id = ?");
        if ($deleteStmt) {
            $deleteStmt->bind_param('i', $councilId);
            $deleteStmt->execute();
            $deleteStmt->close();
        }

        if (!$permissionKeys) {
            return;
        }

        foreach ($permissionKeys as $permissionKey) {
            $permissionKey = trim((string)$permissionKey);
            if ($permissionKey === '') {
                continue;
            }
            $seatPermissionId = GenerateTenDigitMetaID($conn, 'officialseatmodulepermissionstbl', 'seat_permission_id');
            if ($seatPermissionId === false) {
                throw new RuntimeException('Failed to generate seat permission ID.');
            }
            $insertStmt = $conn->prepare("
                INSERT INTO officialseatmodulepermissionstbl
                    (seat_permission_id, council_id, permission_key, is_allowed, granted_by_user_id)
                VALUES
                    (?, ?, ?, 1, NULLIF(?, ''))
            ");
            if (!$insertStmt) {
                throw new RuntimeException('Failed to save seat module permissions.');
            }
            $seatPermissionIdInt = (int)$seatPermissionId;
            $insertStmt->bind_param('iiss', $seatPermissionIdInt, $councilId, $permissionKey, $grantedByUserId);
            $insertStmt->execute();
            $insertStmt->close();
        }
    }
}

if (!function_exists('amp_upsert_seat_access_profile')) {
    function amp_upsert_seat_access_profile(mysqli $conn, int $councilId): void
    {
        amp_ensure_permission_storage($conn);

        if ($councilId <= 0) {
            throw new RuntimeException('Council seat is required to save seat access profile metadata.');
        }

        $stmt = $conn->prepare("
            INSERT INTO officialseataccessprofiletbl (seat_access_profile_id, council_id, permissions_initialized)
            VALUES (?, ?, 1)
            ON DUPLICATE KEY UPDATE
                permissions_initialized = 1,
                updated_at = CURRENT_TIMESTAMP
        ");
        if (!$stmt) {
            throw new RuntimeException('Failed to save seat access profile metadata.');
        }
        $seatAccessProfileId = GenerateTenDigitMetaID($conn, 'officialseataccessprofiletbl', 'seat_access_profile_id');
        if ($seatAccessProfileId === false) {
            throw new RuntimeException('Failed to generate seat access profile ID.');
        }
        $seatAccessProfileIdInt = (int)$seatAccessProfileId;
        $stmt->bind_param('ii', $seatAccessProfileIdInt, $councilId);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('amp_get_protected_code')) {
    function amp_get_protected_code(array $row): string
    {
        $role = amp_normalize_storage_role((string)($row['account_role_access'] ?? $row['info_role_access'] ?? ''));
        $position = trim((string)($row['position_access'] ?? ''));

        if ($role === 'SuperAdmin' && strcasecmp($position, 'IT Administrator') === 0) {
            return 'IT_SUPERADMIN';
        }

        if (strcasecmp($position, 'Barangay Chairman') === 0) {
            return 'BARANGAY_CAPTAIN';
        }

        return '';
    }
}

if (!function_exists('amp_get_protected_label')) {
    function amp_get_protected_label(string $protectedCode): string
    {
        if ($protectedCode === 'IT_SUPERADMIN') {
            return 'Protected IT SuperAdmin';
        }
        if ($protectedCode === 'BARANGAY_CAPTAIN') {
            return 'Barangay Captain';
        }
        return '';
    }
}

if (!function_exists('amp_has_saved_personnel_role_access_profile')) {
    function amp_has_saved_personnel_role_access_profile(mysqli $conn, string $department, string $positionAccess): bool
    {
        amp_ensure_permission_storage($conn);

        $scope = amp_get_personnel_role_profile_scope($department, $positionAccess);
        $stmt = $conn->prepare("
            SELECT 1
            FROM officialaccessroleprofiletbl
            WHERE department_key = ?
              AND position_key = ?
              AND permissions_initialized = 1
            LIMIT 1
        ");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ss', $scope['department_key'], $scope['position_key']);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_row();
        $stmt->close();

        return $row !== null;
    }
}

if (!function_exists('amp_upsert_personnel_role_access_profile')) {
    function amp_upsert_personnel_role_access_profile(mysqli $conn, string $department, string $positionAccess): void
    {
        amp_ensure_permission_storage($conn);

        $scope = amp_get_personnel_role_profile_scope($department, $positionAccess);
        $stmt = $conn->prepare("
            INSERT INTO officialaccessroleprofiletbl
                (role_access_profile_id, department_key, position_key, department_label, position_label, permissions_initialized)
            VALUES
                (?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), 1)
            ON DUPLICATE KEY UPDATE
                department_label = VALUES(department_label),
                position_label = VALUES(position_label),
                permissions_initialized = 1,
                updated_at = CURRENT_TIMESTAMP
        ");
        if (!$stmt) {
            throw new RuntimeException('Failed to save personnel role access profile metadata.');
        }
        $roleAccessProfileId = GenerateTenDigitMetaID($conn, 'officialaccessroleprofiletbl', 'role_access_profile_id');
        if ($roleAccessProfileId === false) {
            throw new RuntimeException('Failed to generate personnel role access profile ID.');
        }
        $roleAccessProfileIdInt = (int)$roleAccessProfileId;
        $stmt->bind_param(
            'issss',
            $roleAccessProfileIdInt,
            $scope['department_key'],
            $scope['position_key'],
            $scope['department_label'],
            $scope['position_label']
        );
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('amp_replace_personnel_role_module_permissions')) {
    function amp_replace_personnel_role_module_permissions(mysqli $conn, string $department, string $positionAccess, array $permissionKeys, string $grantedByUserId): void
    {
        amp_ensure_permission_storage($conn);

        $scope = amp_get_personnel_role_profile_scope($department, $positionAccess);
        $deleteStmt = $conn->prepare("
            DELETE FROM officialaccessrolepermissiontbl
            WHERE department_key = ?
              AND position_key = ?
        ");
        if ($deleteStmt) {
            $deleteStmt->bind_param('ss', $scope['department_key'], $scope['position_key']);
            $deleteStmt->execute();
            $deleteStmt->close();
        }

        if (!$permissionKeys) {
            return;
        }

        foreach ($permissionKeys as $permissionKey) {
            $permissionKey = trim((string)$permissionKey);
            if ($permissionKey === '') {
                continue;
            }
            $rolePermissionId = GenerateTenDigitMetaID($conn, 'officialaccessrolepermissiontbl', 'role_permission_id');
            if ($rolePermissionId === false) {
                throw new RuntimeException('Failed to generate personnel role permission ID.');
            }
            $insertStmt = $conn->prepare("
                INSERT INTO officialaccessrolepermissiontbl
                    (role_permission_id, department_key, position_key, department_label, position_label, permission_key, is_allowed, granted_by_user_id)
                VALUES
                    (?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), ?, 1, NULLIF(?, ''))
            ");
            if (!$insertStmt) {
                throw new RuntimeException('Failed to save personnel role module permissions.');
            }
            $rolePermissionIdInt = (int)$rolePermissionId;
            $insertStmt->bind_param(
                'issssss',
                $rolePermissionIdInt,
                $scope['department_key'],
                $scope['position_key'],
                $scope['department_label'],
                $scope['position_label'],
                $permissionKey,
                $grantedByUserId
            );
            $insertStmt->execute();
            $insertStmt->close();
        }
    }
}

if (!function_exists('amp_delete_personnel_role_access_profile')) {
    function amp_delete_personnel_role_access_profile(mysqli $conn, string $department, string $positionAccess): void
    {
        amp_ensure_permission_storage($conn);

        $scope = amp_get_personnel_role_profile_scope($department, $positionAccess);

        $deletePermissions = $conn->prepare("
            DELETE FROM officialaccessrolepermissiontbl
            WHERE department_key = ?
              AND position_key = ?
        ");
        if ($deletePermissions) {
            $deletePermissions->bind_param('ss', $scope['department_key'], $scope['position_key']);
            $deletePermissions->execute();
            $deletePermissions->close();
        }

        $deleteProfile = $conn->prepare("
            DELETE FROM officialaccessroleprofiletbl
            WHERE department_key = ?
              AND position_key = ?
        ");
        if ($deleteProfile) {
            $deleteProfile->bind_param('ss', $scope['department_key'], $scope['position_key']);
            $deleteProfile->execute();
            $deleteProfile->close();
        }
    }
}

if (!function_exists('amp_get_effective_permission_keys_for_personnel_role')) {
    function amp_get_effective_permission_keys_for_personnel_role(mysqli $conn, string $department, string $positionAccess): array
    {
        amp_ensure_permission_storage($conn);

        $scope = amp_get_personnel_role_profile_scope($department, $positionAccess);
        $hasSavedProfile = amp_has_saved_personnel_role_access_profile($conn, $department, $positionAccess);
        $permissions = [];

        $stmt = $conn->prepare("
            SELECT permission_key
            FROM officialaccessrolepermissiontbl
            WHERE department_key = ?
              AND position_key = ?
              AND is_allowed = 1
        ");
        if ($stmt) {
            $stmt->bind_param('ss', $scope['department_key'], $scope['position_key']);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($permRow = $res->fetch_assoc()) {
                $key = trim((string)($permRow['permission_key'] ?? ''));
                if ($key !== '') {
                    $permissions[$key] = true;
                }
            }
            $stmt->close();
        }

        if (!$permissions && !$hasSavedProfile) {
            foreach (amp_get_default_admin_permission_keys() as $key) {
                $permissions[$key] = true;
            }
        }

        return $permissions;
    }
}

if (!function_exists('amp_get_effective_permission_keys_for_row')) {
    function amp_get_effective_permission_keys_for_row(mysqli $conn, array $row): array
    {
        amp_ensure_permission_storage($conn);

        $displayRole = amp_storage_role_to_display_role((string)($row['account_role_access'] ?? $row['info_role_access'] ?? ''));
        $protectedCode = amp_get_protected_code($row);
        $officialId = trim((string)($row['official_id'] ?? ''));
        $hasSavedProfile = amp_has_saved_access_profile($conn, $officialId);
        $permissions = [];
        $usesSeatTemplate = false;

        // For a seated barangay official, the active council seat template is
        // the source of truth. This prevents copied per-account permissions
        // from drifting away from Official Access Control.
        $activeCouncilId = 0;
        if ($officialId !== '') {
            $seatStmt = $conn->prepare("
                SELECT council_id
                FROM barangaycounciltbl
                WHERE current_official_id = ?
                  AND is_active = 1
                ORDER BY sort_order, council_id
                LIMIT 1
            ");
            if ($seatStmt) {
                $seatStmt->bind_param('s', $officialId);
                $seatStmt->execute();
                $seatRow = $seatStmt->get_result()->fetch_assoc();
                $seatStmt->close();
                $activeCouncilId = (int)($seatRow['council_id'] ?? 0);
            }
        }

        if ($activeCouncilId > 0 && amp_has_saved_seat_access_profile($conn, $activeCouncilId)) {
            $permissions = amp_get_effective_permission_keys_for_council($conn, $activeCouncilId, $displayRole);
            $hasSavedProfile = true;
            $usesSeatTemplate = true;
        }

        if (!$usesSeatTemplate && $officialId !== '') {
            $stmt = $conn->prepare("
                SELECT permission_key
                FROM officialmodulepermissionstbl
                WHERE official_id = ?
                  AND is_allowed = 1
            ");
            if ($stmt) {
                $stmt->bind_param('s', $officialId);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($permRow = $res->fetch_assoc()) {
                    $key = trim((string)($permRow['permission_key'] ?? ''));
                    if ($key !== '') {
                        $permissions[$key] = true;
                    }
                }
                $stmt->close();
            }
        }

        if (!$permissions && !$hasSavedProfile) {
            if ($displayRole !== 'SuperAdmin' && amp_row_uses_personnel_role_profile($row) && $protectedCode !== 'IT_SUPERADMIN') {
                $permissions = amp_get_effective_permission_keys_for_personnel_role(
                    $conn,
                    (string)($row['department'] ?? ''),
                    (string)($row['position_access'] ?? '')
                );
            } else {
                $defaultKeys = $displayRole === 'SuperAdmin'
                    ? amp_get_all_leaf_permission_keys()
                    : amp_get_default_admin_permission_keys();
                foreach ($defaultKeys as $key) {
                    $permissions[$key] = true;
                }
            }
        }

        if ($protectedCode === 'IT_SUPERADMIN') {
            foreach (amp_get_it_superadmin_locked_permission_keys() as $key) {
                $permissions[$key] = true;
            }
        }

        return $permissions;
    }
}

if (!function_exists('amp_get_effective_permission_keys_for_council')) {
    function amp_get_effective_permission_keys_for_council(mysqli $conn, int $councilId, string $displayRole = 'Official'): array
    {
        amp_ensure_permission_storage($conn);

        $permissions = [];

        if ($councilId > 0) {
            $stmt = $conn->prepare("
                SELECT permission_key
                FROM officialseatmodulepermissionstbl
                WHERE council_id = ?
                  AND is_allowed = 1
            ");
            if ($stmt) {
                $stmt->bind_param('i', $councilId);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($permRow = $res->fetch_assoc()) {
                    $key = trim((string)($permRow['permission_key'] ?? ''));
                    if ($key !== '' && !amp_is_admin_only_permission($key)) {
                        $permissions[$key] = true;
                    }
                }
                $stmt->close();
            }
        }

        if (!$permissions && !amp_has_saved_seat_access_profile($conn, $councilId)) {
            $defaultKeys = amp_get_default_admin_permission_keys();
            foreach ($defaultKeys as $key) {
                $permissions[$key] = true;
            }
        }

        return $permissions;
    }
}

if (!function_exists('amp_apply_seat_permissions_to_official')) {
    function amp_apply_seat_permissions_to_official(mysqli $conn, int $councilId, string $officialId, string $userId, string $grantedByUserId, string $displayRole = 'Official'): void
    {
        amp_ensure_permission_storage($conn);

        if ($councilId <= 0 || !amp_has_saved_seat_access_profile($conn, $councilId)) {
            return;
        }

        $permissionKeys = array_keys(amp_get_effective_permission_keys_for_council($conn, $councilId, $displayRole));
        amp_replace_official_module_permissions($conn, $officialId, $userId, $permissionKeys, $grantedByUserId);
        amp_upsert_official_access_profile($conn, $officialId, $userId);
    }
}

if (!function_exists('amp_get_allowed_permission_keys')) {
    function amp_get_allowed_permission_keys(mysqli $conn, string $userId, string $sessionRole = ''): array
    {
        // Permissions are security state, not presentation data. Resolve them
        // from storage on every request so grants and revocations made in
        // Official Access Control take effect immediately.
        $row = $userId !== '' ? amp_get_official_account_by_user_id($conn, $userId) : null;
        if (!$row) {
            $role = amp_storage_role_to_display_role($sessionRole);
            $keys = $role === 'SuperAdmin'
                ? amp_get_all_leaf_permission_keys()
                : amp_get_default_admin_permission_keys();
            return array_fill_keys($keys, true);
        }

        return amp_get_effective_permission_keys_for_row($conn, $row);
    }
}

if (!function_exists('amp_get_first_allowed_path')) {
    function amp_get_first_allowed_path(array $allowedKeys): string
    {
        foreach (amp_get_leaf_permissions() as $key => $meta) {
            if (isset($allowedKeys[$key]) && $allowedKeys[$key] === true && !empty($meta['path'])) {
                return (string)$meta['path'];
            }
        }
        return '';
    }
}

if (!function_exists('amp_area_value_to_key')) {
    function amp_area_value_to_key(string $area): string
    {
        $value = strtolower(trim($area));
        return match ($value) {
            'area 01' => 'area_profile_area_01',
            'area 1a' => 'area_profile_area_1a',
            'area 02' => 'area_profile_area_02',
            'area 03' => 'area_profile_area_03',
            'area 04' => 'area_profile_area_04',
            'area 05' => 'area_profile_area_05',
            'area 06' => 'area_profile_area_06',
            default => 'area_statistics_summary',
        };
    }
}

if (!function_exists('amp_resolve_request_permission_key')) {
    function amp_resolve_request_permission_key(): ?string
    {
        $current = basename((string)($_SERVER['PHP_SELF'] ?? ''));

        return match ($current) {
            'AdminDashboard.php' => 'dashboard',
            'BarangayStatistics.php' => 'dashboard',
            'AppointmentTracker.php' => 'appointments',
            'ResidentTracker.php' => 'resident_masterlist',
            'ResidentMasterlist.php' => 'resident_masterlist',
            'EditRequests.php' => 'resident_edit_requests',
            'ResidentArchive.php' => 'resident_archive',
            'SectorMembershipVerification.php' => 'resident_sector_membership_verification',
            'HouseholdProfiling.php' => 'household_profiling_main',
            'HeadOfTheFamilyVerification.php' => 'head_of_family_verification',
            'HouseholdMemberVerification.php' => 'household_member_verification',
            'AreaStatistics.php' => 'area_statistics_summary',
            'AreaProfile.php' => amp_area_value_to_key((string)($_GET['area'] ?? '')),
            'BusinessMonitoring.php' => 'business_monitoring',
            'BlotterForm.php' => 'blotter_log_new_incident',
            'BlotterTracker.php' => 'blotter_tracker',
            'ReviewQueue.php' => 'blotter_review_queue',
            'ComplaintTracker.php' => 'complaint_tracker',
            'ComplaintForm.php' => 'complaint_log_new_incident',
            'ContentManagement.php' => 'announcements_tracker',
            'Contents.php' => strtolower(trim((string)($_GET['type_filter'] ?? 'all'))) === 'news'
                ? 'news_management'
                : 'announcements_tracker',
            'CreateNews.php' => 'news_management',
            'CreateContent.php' => match (strtolower(trim((string)($_GET['type'] ?? 'page')))) {
                'delivery' => 'announcements_delivery',
                'faq' => 'announcements_faq',
                default => 'announcements_page',
            },
            'Reports.php' => match (strtolower(trim((string)($_GET['module'] ?? 'certificate_issuance')))) {
                'clearance_issuance' => 'reports_clearance_issuance',
                'financial', 'document_requests' => 'reports_financial',
                'residents' => 'reports_residents',
                'blotter' => 'reports_blotter',
                'complaints' => 'reports_complaints',
                default => 'reports_certificate_issuance',
            },
            'UserMasterlist.php' => 'user_masterlist',
            'UserArchive.php' => 'user_archive',
            'AdminManagement.php' => 'admin_management',
            'PersonnelTracker.php' => 'officials_management',
            'PersonnelRoleAccess.php' => 'officials_management',
            'OfficialsManagement.php' => 'official_records_management',
            'OfficialInvites.php' => 'personnel_invite',
            'OfficialTransitions.php' => 'official_transition',
            'AuditLogs.php' => 'audit_logs',
            'WebsiteSettings.php' => 'website_settings',
            'CertificateIssuanceSettings.php' => 'certificate_issuance',
            'BusinessMonitoringSettings.php' => 'clearance_issuance',
            'ClearanceDocumentSettings.php' => 'clearance_issuance',
            'ClearanceGeneralSettings.php' => 'clearance_issuance',
            'ClearanceTypeSettings.php' => 'clearance_issuance',
            'ClearanceNotificationSettings.php' => 'clearance_issuance',
            'BarangayIdSettings.php' => 'id_issuance_tracker',
            'FinancePayments.php' => match (strtolower(trim((string)($_GET['section'] ?? 'tracker')))) {
                'create' => 'finance_create_transaction',
                'fees' => 'finance_fee_management',
                'settings' => 'finance_payment_settings',
                default => 'finance_payment_tracker',
            },
            'CertificateTracker.php' => amp_resolve_certificate_tracker_permission_key(),
            default => null,
        };
    }
}

if (!function_exists('amp_resolve_certificate_tracker_permission_key')) {
    function amp_resolve_certificate_tracker_permission_key(): string
    {
        $tab = strtolower(trim((string)($_GET['tab'] ?? 'tracker')));
        $document = strtolower(trim((string)($_GET['document'] ?? '')));
        $entry = strtolower(trim((string)($_GET['entry'] ?? '')));
        $stage = strtolower(trim((string)($_GET['stage'] ?? '')));
        $filterDocument = strtolower(trim((string)($_GET['filter_document'] ?? '')));

        if ($tab === 'manual' && $document === 'barangay_id') {
            return 'id_issuance_manual';
        }

        if ($entry === 'id_issuance' || $stage === 'barangay_id' || $filterDocument === 'barangay id') {
            return 'id_issuance_tracker';
        }

        if (str_contains($filterDocument, 'clearance') || $filterDocument === '__clearances__') {
            return 'clearance_issuance';
        }

        return 'certificate_issuance';
    }
}

if (!function_exists('amp_is_account_expired')) {
    function amp_is_account_expired(array $row): bool
    {
        $termEnd = trim((string)($row['term_end'] ?? ''));
        if ($termEnd === '') {
            return false;
        }

        try {
            $expiry = new DateTimeImmutable($termEnd);
            $today = new DateTimeImmutable('today');
            return $expiry < $today;
        } catch (Throwable) {
            return false;
        }
    }
}

if (!function_exists('amp_force_expired_account_inactive')) {
    function amp_force_expired_account_inactive(mysqli $conn, array $row): void
    {
        if (!amp_is_account_expired($row)) {
            return;
        }

        $inactiveStatusId = amp_get_status_id_by_names($conn, 'UserAccount', ['Inactive', 'Revoked', 'Suspended', 'Disabled']);
        $userId = trim((string)($row['user_id'] ?? ''));
        if ($inactiveStatusId === null || $userId === '') {
            return;
        }

        $stmt = $conn->prepare("UPDATE useraccountstbl SET status_id_account = ?, updated_at = NOW() WHERE user_id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('is', $inactiveStatusId, $userId);
            $stmt->execute();
            $stmt->close();
        }
    }
}

if (!function_exists('amp_get_oldest_active_superadmin_user_id')) {
    function amp_get_oldest_active_superadmin_user_id(mysqli $conn): string
    {
        $activeId = amp_get_status_id_by_names($conn, 'UserAccount', ['Active']);
        $inactiveId = amp_get_status_id_by_names($conn, 'UserAccount', ['Inactive', 'Revoked', 'Suspended', 'Disabled']);

        $sql = "
            SELECT user_id
            FROM useraccountstbl
            WHERE role_access = 'SuperAdmin'
        ";
        $params = [];
        $types = '';

        if ($activeId !== null) {
            $sql .= " AND status_id_account = ?";
            $types .= 'i';
            $params[] = (int)$activeId;
        } elseif ($inactiveId !== null) {
            $sql .= " AND status_id_account <> ?";
            $types .= 'i';
            $params[] = (int)$inactiveId;
        }

        $sql .= "
            ORDER BY
                CASE WHEN account_created IS NULL THEN 1 ELSE 0 END ASC,
                account_created ASC,
                user_id ASC
            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return '';
        }

        if ($types !== '') {
            $refs = [];
            $refs[] = $types;
            foreach ($params as $idx => $value) {
                $refs[] = &$params[$idx];
            }
            call_user_func_array([$stmt, 'bind_param'], $refs);
        }

        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return trim((string)($row['user_id'] ?? ''));
    }
}

if (!function_exists('amp_is_oldest_active_superadmin')) {
    function amp_is_oldest_active_superadmin(mysqli $conn, string $userId): bool
    {
        $userId = trim($userId);
        if ($userId === '') {
            return false;
        }

        $oldestUserId = amp_get_oldest_active_superadmin_user_id($conn);
        return $oldestUserId !== '' && strcasecmp($oldestUserId, $userId) === 0;
    }
}

if (!function_exists('amp_get_superadmin_management_disabled_reason')) {
    function amp_get_superadmin_management_disabled_reason(mysqli $conn, string $actorUserId, string $targetDisplayRole): string
    {
        if (amp_storage_role_to_display_role($targetDisplayRole) !== 'SuperAdmin') {
            return '';
        }

        $actorUserId = trim($actorUserId);
        if ($actorUserId === '') {
            return 'Unable to verify the current SuperAdmin account.';
        }

        $oldestUserId = amp_get_oldest_active_superadmin_user_id($conn);
        if ($oldestUserId === '') {
            return 'Only the oldest active SuperAdmin account can manage SuperAdmin accounts, but none is currently eligible.';
        }

        if (strcasecmp($oldestUserId, $actorUserId) !== 0) {
            return 'Only the oldest active SuperAdmin account can manage SuperAdmin accounts.';
        }

        return '';
    }
}

if (!function_exists('amp_count_active_superadmins_excluding')) {
    function amp_count_active_superadmins_excluding(mysqli $conn, string $excludeUserId = ''): int
    {
        $activeId = amp_get_status_id_by_names($conn, 'UserAccount', ['Active']);
        $inactiveId = amp_get_status_id_by_names($conn, 'UserAccount', ['Inactive', 'Revoked', 'Suspended', 'Disabled']);
        $sql = "
            SELECT COUNT(*) AS cnt
            FROM useraccountstbl
            WHERE role_access = 'SuperAdmin'
        ";
        $params = [];
        $types = '';

        if ($activeId !== null) {
            $sql .= " AND status_id_account = ?";
            $types .= 'i';
            $params[] = (int)$activeId;
        } elseif ($inactiveId !== null) {
            $sql .= " AND status_id_account <> ?";
            $types .= 'i';
            $params[] = (int)$inactiveId;
        }
        if ($excludeUserId !== '') {
            $sql .= " AND user_id <> ?";
            $types .= 's';
            $params[] = $excludeUserId;
        }

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return 0;
        }

        if ($types !== '') {
            $refs = [];
            $refs[] = $types;
            foreach ($params as $idx => $value) {
                $refs[] = &$params[$idx];
            }
            call_user_func_array([$stmt, 'bind_param'], $refs);
        }

        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int)($row['cnt'] ?? 0);
    }
}
