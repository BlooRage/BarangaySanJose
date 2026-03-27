<?php
declare(strict_types=1);

require_once __DIR__ . '/runtimeConfig.php';

if (!function_exists('pii_default_secret_map')) {
    function pii_default_secret_map(): array
    {
        return [
            'security.pii_key' => 'base64:Ww/sIEHabQg10jTzoOSNI+Wp7rr4DhW1OnbPsvaD/5E=',
            'security.pii_hash_key' => 'base64:KcXULUj0GKxH/HOI7mZ56xnzO9axxPmkaUmacTm9gXQ=',
        ];
    }
}

if (!function_exists('pii_secret_value')) {
    function pii_secret_value(string $envKey, string $configKey): string
    {
        $defaults = pii_default_secret_map();
        return trim((string)runtime_env($envKey, runtime_config($configKey, $defaults[$configKey] ?? '')));
    }
}

if (!function_exists('pii_decode_secret')) {
    function pii_decode_secret(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (str_starts_with($value, 'base64:')) {
            $value = substr($value, 7);
        }

        $decoded = base64_decode($value, true);
        if ($decoded !== false && $decoded !== '') {
            return $decoded;
        }

        return $value;
    }
}

if (!function_exists('pii_master_key')) {
    function pii_master_key(): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $secret = pii_decode_secret(pii_secret_value('APP_PII_KEY', 'security.pii_key'));
        if ($secret === '') {
            return $cached = '';
        }

        return $cached = hash('sha256', $secret, true);
    }
}

if (!function_exists('pii_lookup_key')) {
    function pii_lookup_key(): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $secret = pii_decode_secret(pii_secret_value('APP_PII_HASH_KEY', 'security.pii_hash_key'));
        if ($secret === '') {
            $master = pii_master_key();
            if ($master === '') {
                return $cached = '';
            }
            return $cached = hash_hmac('sha256', 'lookup-index', $master, true);
        }

        return $cached = hash('sha256', $secret, true);
    }
}

if (!function_exists('pii_lookup_key_from_secret')) {
    function pii_lookup_key_from_secret(string $secret): string
    {
        $secret = pii_decode_secret($secret);
        if ($secret === '') {
            return '';
        }

        return hash('sha256', $secret, true);
    }
}

if (!function_exists('pii_lookup_key_candidates')) {
    function pii_lookup_key_candidates(): array
    {
        $defaults = pii_default_secret_map();
        $candidates = [];

        $active = pii_lookup_key();
        if ($active !== '') {
            $candidates[] = $active;
        }

        $configKey = pii_lookup_key_from_secret((string)runtime_config('security.pii_hash_key', $defaults['security.pii_hash_key'] ?? ''));
        if ($configKey !== '') {
            $candidates[] = $configKey;
        }

        $defaultKey = pii_lookup_key_from_secret((string)($defaults['security.pii_hash_key'] ?? ''));
        if ($defaultKey !== '') {
            $candidates[] = $defaultKey;
        }

        return array_values(array_unique($candidates));
    }
}

if (!function_exists('pii_is_enabled')) {
    function pii_is_enabled(): bool
    {
        return pii_master_key() !== '';
    }
}

if (!function_exists('pii_cipher_prefix')) {
    function pii_cipher_prefix(): string
    {
        return 'pii:v1:';
    }
}

if (!function_exists('pii_is_encrypted_value')) {
    function pii_is_encrypted_value($value): bool
    {
        return is_string($value) && str_starts_with($value, pii_cipher_prefix());
    }
}

if (!function_exists('pii_base64url_encode')) {
    function pii_base64url_encode(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }
}

if (!function_exists('pii_base64url_decode')) {
    function pii_base64url_decode(string $encoded): ?string
    {
        $encoded = trim($encoded);
        if ($encoded === '') {
            return null;
        }
        $encoded = strtr($encoded, '-_', '+/');
        $pad = strlen($encoded) % 4;
        if ($pad > 0) {
            $encoded .= str_repeat('=', 4 - $pad);
        }
        $decoded = base64_decode($encoded, true);
        return $decoded === false ? null : $decoded;
    }
}

if (!function_exists('pii_encrypt_string')) {
    function pii_encrypt_string($value)
    {
        if ($value === null) {
            return null;
        }

        $plain = (string)$value;
        if ($plain === '' || pii_is_encrypted_value($plain) || !pii_is_enabled()) {
            return $plain;
        }

        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plain,
            'aes-256-gcm',
            pii_master_key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            pii_cipher_prefix()
        );

        if ($ciphertext === false || $tag === '') {
            return $plain;
        }

        return pii_cipher_prefix() . pii_base64url_encode($iv . $tag . $ciphertext);
    }
}

if (!function_exists('pii_decrypt_string')) {
    function pii_decrypt_string($value): string
    {
        if ($value === null) {
            return '';
        }

        $cipher = (string)$value;
        if ($cipher === '' || !pii_is_encrypted_value($cipher) || !pii_is_enabled()) {
            return $cipher;
        }

        $payload = substr($cipher, strlen(pii_cipher_prefix()));
        $decoded = pii_base64url_decode($payload);
        if ($decoded === null || strlen($decoded) < 29) {
            return $cipher;
        }

        $iv = substr($decoded, 0, 12);
        $tag = substr($decoded, 12, 16);
        $ciphertext = substr($decoded, 28);
        $plain = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            pii_master_key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            pii_cipher_prefix()
        );

        return $plain === false ? $cipher : $plain;
    }
}

if (!function_exists('pii_normalize_email')) {
    function pii_normalize_email(string $value): string
    {
        return strtolower(trim($value));
    }
}

if (!function_exists('pii_normalize_phone10')) {
    function pii_normalize_phone10(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw);
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }
        if (strlen($digits) === 12 && str_starts_with($digits, '63')) {
            $digits = substr($digits, 2);
        }
        return substr($digits, 0, 10);
    }
}

if (!function_exists('pii_lookup_hash')) {
    function pii_lookup_hash(string $value, string $purpose = 'generic'): string
    {
        if ($value === '' || pii_lookup_key() === '') {
            return '';
        }

        return hash_hmac('sha256', $purpose . ':' . $value, pii_lookup_key());
    }
}

if (!function_exists('pii_lookup_hash_candidates')) {
    function pii_lookup_hash_candidates(string $value, string $purpose = 'generic'): array
    {
        if ($value === '') {
            return [];
        }

        $hashes = [];
        foreach (pii_lookup_key_candidates() as $lookupKey) {
            if ($lookupKey === '') {
                continue;
            }
            $hashes[] = hash_hmac('sha256', $purpose . ':' . $value, $lookupKey);
        }

        $activeHash = pii_lookup_hash($value, $purpose);
        if ($activeHash !== '') {
            $hashes[] = $activeHash;
        }

        return array_values(array_unique(array_filter($hashes, static fn($hash): bool => $hash !== '')));
    }
}

if (!function_exists('pii_select_first_useraccount_by_lookup_hashes')) {
    function pii_select_first_useraccount_by_lookup_hashes(mysqli $conn, string $lookupColumn, array $hashes, array $columns = ['user_id']): ?array
    {
        if (!in_array($lookupColumn, ['email_lookup_hash', 'phone_lookup_hash'], true)) {
            return null;
        }

        $hashes = array_values(array_unique(array_filter(array_map(
            static fn($value): string => trim((string)$value),
            $hashes
        ), static fn(string $value): bool => $value !== '')));
        if ($hashes === []) {
            return null;
        }

        $columns = array_values(array_unique(array_filter(array_merge($columns, ['user_id']), static function ($column): bool {
            return is_string($column) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column) === 1;
        })));
        if ($columns === []) {
            $columns = ['user_id'];
        }

        $select = implode(', ', array_map(static fn(string $column): string => "`{$column}`", $columns));
        $stmt = $conn->prepare("SELECT {$select} FROM useraccountstbl WHERE {$lookupColumn} = ? LIMIT 1");
        if (!$stmt) {
            return null;
        }

        foreach ($hashes as $hash) {
            $stmt->bind_param('s', $hash);
            if (!$stmt->execute()) {
                continue;
            }
            $result = $stmt->get_result();
            if ($result instanceof mysqli_result) {
                $row = $result->fetch_assoc();
                if (is_array($row)) {
                    $stmt->close();
                    return $row;
                }
            }
        }

        $stmt->close();
        return null;
    }
}

if (!function_exists('pii_select_first_useraccount_by_contact_hashes')) {
    function pii_select_first_useraccount_by_contact_hashes(mysqli $conn, array $emailHashes, array $phoneHashes, array $columns = ['user_id']): ?array
    {
        $emailHashes = array_values(array_unique(array_filter(array_map(
            static fn($value): string => trim((string)$value),
            $emailHashes
        ), static fn(string $value): bool => $value !== '')));
        $phoneHashes = array_values(array_unique(array_filter(array_map(
            static fn($value): string => trim((string)$value),
            $phoneHashes
        ), static fn(string $value): bool => $value !== '')));

        if ($emailHashes === [] || $phoneHashes === []) {
            return null;
        }

        $columns = array_values(array_unique(array_filter(array_merge($columns, ['user_id']), static function ($column): bool {
            return is_string($column) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column) === 1;
        })));
        if ($columns === []) {
            $columns = ['user_id'];
        }

        $select = implode(', ', array_map(static fn(string $column): string => "`{$column}`", $columns));
        $stmt = $conn->prepare("
            SELECT {$select}
            FROM useraccountstbl
            WHERE email_lookup_hash = ? AND phone_lookup_hash = ?
            LIMIT 1
        ");
        if (!$stmt) {
            return null;
        }

        foreach ($emailHashes as $emailHash) {
            foreach ($phoneHashes as $phoneHash) {
                $stmt->bind_param('ss', $emailHash, $phoneHash);
                if (!$stmt->execute()) {
                    continue;
                }
                $result = $stmt->get_result();
                if ($result instanceof mysqli_result) {
                    $row = $result->fetch_assoc();
                    if (is_array($row)) {
                        $stmt->close();
                        return $row;
                    }
                }
            }
        }

        $stmt->close();
        return null;
    }
}

if (!function_exists('pii_useraccount_fields')) {
    function pii_useraccount_fields(): array
    {
        return ['email', 'phone_number'];
    }
}

if (!function_exists('pii_resident_fields')) {
    function pii_resident_fields(): array
    {
        return [
            'lastname',
            'firstname',
            'middlename',
            'suffix',
            'birthdate',
            'birthplace',
            'civil_status',
            'family_role',
            'occupation_detail',
            'religion',
            'baranagayresidency',
        ];
    }
}

if (!function_exists('pii_resident_address_fields')) {
    function pii_resident_address_fields(): array
    {
        return [
            'unit_number',
            'street_number',
            'street_name',
            'phase_number',
            'subdivision',
            'house_type',
            'house_ownership',
            'residency_duration',
        ];
    }
}

if (!function_exists('pii_official_fields')) {
    function pii_official_fields(): array
    {
        return [
            'lastname',
            'firstname',
            'middlename',
            'suffix',
            'birthdate',
            'sex',
            'civil_status',
            'contact_number',
            'email',
            'emergency_contact_name',
            'emergency_contact_relationship',
            'emergency_contact_phone',
            'emergency_contact_address',
            'house_number',
            'street_name',
            'barangay',
            'municipality_city',
            'province',
            'address_mode',
            'block_number',
            'lot_number',
        ];
    }
}

if (!function_exists('pii_emergency_contact_fields')) {
    function pii_emergency_contact_fields(): array
    {
        return [
            'last_name',
            'first_name',
            'middle_name',
            'suffix',
            'phone_number',
            'relationship',
            'address',
        ];
    }
}

if (!function_exists('pii_official_invite_fields')) {
    function pii_official_invite_fields(): array
    {
        return [
            'invite_email',
            'invite_phone',
            'firstname',
            'middlename',
            'lastname',
            'suffix',
        ];
    }
}

if (!function_exists('pii_decrypt_assoc')) {
    function pii_decrypt_assoc(array $row, array $fields): array
    {
        foreach ($fields as $field) {
            if (!array_key_exists($field, $row)) {
                continue;
            }
            if ($row[$field] === null) {
                continue;
            }
            $row[$field] = pii_decrypt_string($row[$field]);
        }
        return $row;
    }
}

if (!function_exists('pii_search_match')) {
    function pii_search_match(array $row, array $fields, string $needle): bool
    {
        $needle = strtolower(trim($needle));
        if ($needle === '') {
            return true;
        }

        foreach ($fields as $field) {
            $value = trim((string)($row[$field] ?? ''));
            if ($value !== '' && stripos($value, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('pii_table_exists')) {
    function pii_table_exists(mysqli $conn, string $table): bool
    {
        $safeTable = $conn->real_escape_string($table);
        $res = $conn->query("SHOW TABLES LIKE '{$safeTable}'");
        return $res instanceof mysqli_result && $res->num_rows > 0;
    }
}

if (!function_exists('pii_column_exists')) {
    function pii_column_exists(mysqli $conn, string $table, string $column): bool
    {
        $safeTable = $conn->real_escape_string($table);
        $safeColumn = $conn->real_escape_string($column);
        $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
        return $res instanceof mysqli_result && $res->num_rows > 0;
    }
}

if (!function_exists('pii_index_exists')) {
    function pii_index_exists(mysqli $conn, string $table, string $index): bool
    {
        $safeTable = $conn->real_escape_string($table);
        $safeIndex = $conn->real_escape_string($index);
        $res = $conn->query("SHOW INDEX FROM `{$safeTable}` WHERE Key_name = '{$safeIndex}'");
        return $res instanceof mysqli_result && $res->num_rows > 0;
    }
}

if (!function_exists('pii_encrypt_field_map')) {
    function pii_encrypt_field_map(array $values): array
    {
        $out = [];
        foreach ($values as $field => $value) {
            $out[$field] = pii_encrypt_string($value);
        }
        return $out;
    }
}

if (!function_exists('pii_prepare_useraccount_contacts')) {
    function pii_prepare_useraccount_contacts(string $email, string $phone10): array
    {
        $normalizedEmail = pii_normalize_email($email);
        $normalizedPhone = pii_normalize_phone10($phone10);

        return [
            'email' => pii_encrypt_string($normalizedEmail),
            'phone_number' => pii_encrypt_string($normalizedPhone),
            'email_lookup_hash' => $normalizedEmail !== '' ? pii_lookup_hash($normalizedEmail, 'useraccount.email') : null,
            'phone_lookup_hash' => $normalizedPhone !== '' ? pii_lookup_hash($normalizedPhone, 'useraccount.phone') : null,
        ];
    }
}

if (!function_exists('pii_prepare_official_invite_contacts')) {
    function pii_prepare_official_invite_contacts(string $email, string $phone10): array
    {
        $normalizedEmail = pii_normalize_email($email);
        $normalizedPhone = pii_normalize_phone10($phone10);

        return [
            'invite_email' => pii_encrypt_string($normalizedEmail),
            'invite_phone' => pii_encrypt_string($normalizedPhone),
            'invite_email_lookup_hash' => $normalizedEmail !== '' ? pii_lookup_hash($normalizedEmail, 'officialinvite.email') : null,
            'invite_phone_lookup_hash' => $normalizedPhone !== '' ? pii_lookup_hash($normalizedPhone, 'officialinvite.phone') : null,
        ];
    }
}

if (!function_exists('pii_decrypt_useraccount_row')) {
    function pii_decrypt_useraccount_row(?array $row): ?array
    {
        return is_array($row) ? pii_decrypt_assoc($row, pii_useraccount_fields()) : null;
    }
}

if (!function_exists('pii_decrypt_resident_row')) {
    function pii_decrypt_resident_row(?array $row): ?array
    {
        return is_array($row) ? pii_decrypt_assoc($row, pii_resident_fields()) : null;
    }
}

if (!function_exists('pii_decrypt_resident_address_row')) {
    function pii_decrypt_resident_address_row(?array $row): ?array
    {
        return is_array($row) ? pii_decrypt_assoc($row, pii_resident_address_fields()) : null;
    }
}

if (!function_exists('pii_decrypt_official_row')) {
    function pii_decrypt_official_row(?array $row): ?array
    {
        return is_array($row) ? pii_decrypt_assoc($row, pii_official_fields()) : null;
    }
}

if (!function_exists('pii_decrypt_emergency_contact_row')) {
    function pii_decrypt_emergency_contact_row(?array $row): ?array
    {
        return is_array($row) ? pii_decrypt_assoc($row, pii_emergency_contact_fields()) : null;
    }
}

if (!function_exists('pii_decrypt_official_invite_row')) {
    function pii_decrypt_official_invite_row(?array $row): ?array
    {
        return is_array($row) ? pii_decrypt_assoc($row, pii_official_invite_fields()) : null;
    }
}

if (!function_exists('pii_ensure_core_schema')) {
    function pii_ensure_core_schema(mysqli $conn): void
    {
        static $done = false;
        if ($done || !pii_is_enabled()) {
            return;
        }
        $done = true;

        if (pii_table_exists($conn, 'useraccountstbl')) {
            $conn->query("ALTER TABLE useraccountstbl MODIFY COLUMN phone_number VARCHAR(255) NOT NULL");
            $conn->query("ALTER TABLE useraccountstbl MODIFY COLUMN email VARCHAR(255) NOT NULL");
            if (!pii_column_exists($conn, 'useraccountstbl', 'email_lookup_hash')) {
                $conn->query("ALTER TABLE useraccountstbl ADD COLUMN email_lookup_hash CHAR(64) NULL AFTER email");
            }
            if (!pii_column_exists($conn, 'useraccountstbl', 'phone_lookup_hash')) {
                $conn->query("ALTER TABLE useraccountstbl ADD COLUMN phone_lookup_hash CHAR(64) NULL AFTER phone_number");
            }
            if (!pii_index_exists($conn, 'useraccountstbl', 'uq_useraccount_email_lookup_hash')) {
                $conn->query("ALTER TABLE useraccountstbl ADD UNIQUE KEY uq_useraccount_email_lookup_hash (email_lookup_hash)");
            }
            if (!pii_index_exists($conn, 'useraccountstbl', 'uq_useraccount_phone_lookup_hash')) {
                $conn->query("ALTER TABLE useraccountstbl ADD UNIQUE KEY uq_useraccount_phone_lookup_hash (phone_lookup_hash)");
            }
        }

        if (pii_table_exists($conn, 'residentinformationtbl')) {
            $conn->query("ALTER TABLE residentinformationtbl MODIFY COLUMN lastname VARCHAR(255) NOT NULL");
            $conn->query("ALTER TABLE residentinformationtbl MODIFY COLUMN firstname VARCHAR(255) NOT NULL");
            $conn->query("ALTER TABLE residentinformationtbl MODIFY COLUMN middlename VARCHAR(255) NULL");
            $conn->query("ALTER TABLE residentinformationtbl MODIFY COLUMN suffix VARCHAR(255) NULL");
            $conn->query("ALTER TABLE residentinformationtbl MODIFY COLUMN birthdate VARCHAR(255) NOT NULL");
            $conn->query("ALTER TABLE residentinformationtbl MODIFY COLUMN birthplace VARCHAR(512) NULL");
            $conn->query("ALTER TABLE residentinformationtbl MODIFY COLUMN baranagayresidency VARCHAR(255) NULL");
            $conn->query("ALTER TABLE residentinformationtbl MODIFY COLUMN civil_status VARCHAR(255) NOT NULL");
            $conn->query("ALTER TABLE residentinformationtbl MODIFY COLUMN family_role VARCHAR(255) NULL");
            $conn->query("ALTER TABLE residentinformationtbl MODIFY COLUMN occupation_detail VARCHAR(255) NULL");
            $conn->query("ALTER TABLE residentinformationtbl MODIFY COLUMN religion VARCHAR(255) NULL");
        }

        if (pii_table_exists($conn, 'residentaddresstbl')) {
            $conn->query("ALTER TABLE residentaddresstbl MODIFY COLUMN unit_number VARCHAR(255) NULL");
            $conn->query("ALTER TABLE residentaddresstbl MODIFY COLUMN street_number VARCHAR(255) NULL");
            $conn->query("ALTER TABLE residentaddresstbl MODIFY COLUMN street_name VARCHAR(255) NULL");
            $conn->query("ALTER TABLE residentaddresstbl MODIFY COLUMN phase_number VARCHAR(255) NULL");
            $conn->query("ALTER TABLE residentaddresstbl MODIFY COLUMN subdivision VARCHAR(255) NULL");
            $conn->query("ALTER TABLE residentaddresstbl MODIFY COLUMN house_type VARCHAR(255) NULL");
            $conn->query("ALTER TABLE residentaddresstbl MODIFY COLUMN house_ownership VARCHAR(255) NULL");
            $conn->query("ALTER TABLE residentaddresstbl MODIFY COLUMN residency_duration VARCHAR(255) NULL");
        }

        if (pii_table_exists($conn, 'officialinformationtbl')) {
            $conn->query("ALTER TABLE officialinformationtbl MODIFY COLUMN lastname VARCHAR(255) NOT NULL");
            $conn->query("ALTER TABLE officialinformationtbl MODIFY COLUMN firstname VARCHAR(255) NOT NULL");
            $conn->query("ALTER TABLE officialinformationtbl MODIFY COLUMN middlename VARCHAR(255) NULL");
            $conn->query("ALTER TABLE officialinformationtbl MODIFY COLUMN suffix VARCHAR(255) NULL");
            $conn->query("ALTER TABLE officialinformationtbl MODIFY COLUMN birthdate VARCHAR(255) NOT NULL");
            $conn->query("ALTER TABLE officialinformationtbl MODIFY COLUMN sex VARCHAR(255) NOT NULL");
            $conn->query("ALTER TABLE officialinformationtbl MODIFY COLUMN civil_status VARCHAR(255) NOT NULL");
            $conn->query("ALTER TABLE officialinformationtbl MODIFY COLUMN contact_number VARCHAR(255) NOT NULL");
            $conn->query("ALTER TABLE officialinformationtbl MODIFY COLUMN email VARCHAR(255) NOT NULL");
            $conn->query("ALTER TABLE officialinformationtbl MODIFY COLUMN emergency_contact_name VARCHAR(255) NULL");
            $conn->query("ALTER TABLE officialinformationtbl MODIFY COLUMN emergency_contact_relationship VARCHAR(255) NULL");
            $conn->query("ALTER TABLE officialinformationtbl MODIFY COLUMN emergency_contact_phone VARCHAR(255) NULL");
            $conn->query("ALTER TABLE officialinformationtbl MODIFY COLUMN emergency_contact_address VARCHAR(512) NULL");
            $conn->query("ALTER TABLE officialinformationtbl MODIFY COLUMN house_number VARCHAR(255) NULL");
            $conn->query("ALTER TABLE officialinformationtbl MODIFY COLUMN street_name VARCHAR(255) NULL");
            $conn->query("ALTER TABLE officialinformationtbl MODIFY COLUMN barangay VARCHAR(255) NULL");
            $conn->query("ALTER TABLE officialinformationtbl MODIFY COLUMN municipality_city VARCHAR(255) NULL");
            $conn->query("ALTER TABLE officialinformationtbl MODIFY COLUMN province VARCHAR(255) NULL");
            $conn->query("ALTER TABLE officialinformationtbl MODIFY COLUMN address_mode VARCHAR(255) NULL");
            $conn->query("ALTER TABLE officialinformationtbl MODIFY COLUMN block_number VARCHAR(255) NULL");
            $conn->query("ALTER TABLE officialinformationtbl MODIFY COLUMN lot_number VARCHAR(255) NULL");
        }

        if (pii_table_exists($conn, 'emergencycontacttbl')) {
            $conn->query("ALTER TABLE emergencycontacttbl MODIFY COLUMN last_name VARCHAR(255) NOT NULL");
            $conn->query("ALTER TABLE emergencycontacttbl MODIFY COLUMN first_name VARCHAR(255) NOT NULL");
            $conn->query("ALTER TABLE emergencycontacttbl MODIFY COLUMN middle_name VARCHAR(255) NULL");
            $conn->query("ALTER TABLE emergencycontacttbl MODIFY COLUMN suffix VARCHAR(255) NULL");
            $conn->query("ALTER TABLE emergencycontacttbl MODIFY COLUMN phone_number VARCHAR(255) NOT NULL");
            $conn->query("ALTER TABLE emergencycontacttbl MODIFY COLUMN relationship VARCHAR(255) NOT NULL");
            $conn->query("ALTER TABLE emergencycontacttbl MODIFY COLUMN address VARCHAR(512) NOT NULL");
        }

        if (pii_table_exists($conn, 'officialinvitetbl')) {
            $conn->query("ALTER TABLE officialinvitetbl MODIFY COLUMN invite_email VARCHAR(255) NOT NULL");
            $conn->query("ALTER TABLE officialinvitetbl MODIFY COLUMN invite_phone VARCHAR(255) NOT NULL");
            $conn->query("ALTER TABLE officialinvitetbl MODIFY COLUMN firstname VARCHAR(255) NOT NULL");
            $conn->query("ALTER TABLE officialinvitetbl MODIFY COLUMN middlename VARCHAR(255) NULL");
            $conn->query("ALTER TABLE officialinvitetbl MODIFY COLUMN lastname VARCHAR(255) NOT NULL");
            $conn->query("ALTER TABLE officialinvitetbl MODIFY COLUMN suffix VARCHAR(255) NULL");
            if (!pii_column_exists($conn, 'officialinvitetbl', 'invite_email_lookup_hash')) {
                $conn->query("ALTER TABLE officialinvitetbl ADD COLUMN invite_email_lookup_hash CHAR(64) NULL AFTER invite_email");
            }
            if (!pii_column_exists($conn, 'officialinvitetbl', 'invite_phone_lookup_hash')) {
                $conn->query("ALTER TABLE officialinvitetbl ADD COLUMN invite_phone_lookup_hash CHAR(64) NULL AFTER invite_phone");
            }
            if (!pii_index_exists($conn, 'officialinvitetbl', 'idx_officialinvite_email_hash')) {
                $conn->query("ALTER TABLE officialinvitetbl ADD KEY idx_officialinvite_email_hash (invite_email_lookup_hash)");
            }
            if (!pii_index_exists($conn, 'officialinvitetbl', 'idx_officialinvite_phone_hash')) {
                $conn->query("ALTER TABLE officialinvitetbl ADD KEY idx_officialinvite_phone_hash (invite_phone_lookup_hash)");
            }
        }
    }
}

if (!function_exists('pii_backfill_simple_table')) {
    function pii_backfill_simple_table(mysqli $conn, string $table, string $primaryKey, array $fields): int
    {
        if (!pii_table_exists($conn, $table) || $fields === []) {
            return 0;
        }

        $selectCols = array_map(static fn(string $value): string => "`{$value}`", array_merge([$primaryKey], $fields));
        $res = $conn->query("SELECT " . implode(', ', $selectCols) . " FROM `{$table}`");
        if (!($res instanceof mysqli_result)) {
            return 0;
        }

        $updated = 0;
        while ($row = $res->fetch_assoc()) {
            $changes = [];
            foreach ($fields as $field) {
                $original = $row[$field] ?? null;
                $plain = $original === null ? null : pii_decrypt_string($original);
                $encrypted = $plain === null ? null : pii_encrypt_string($plain);
                if ($encrypted !== $original) {
                    $changes[$field] = $encrypted;
                }
            }

            if ($changes === []) {
                continue;
            }

            $setParts = [];
            $params = [];
            $types = '';
            foreach ($changes as $field => $value) {
                $setParts[] = "`{$field}` = ?";
                $params[] = $value;
                $types .= 's';
            }
            $params[] = (string)($row[$primaryKey] ?? '');
            $types .= 's';

            $stmt = $conn->prepare("UPDATE `{$table}` SET " . implode(', ', $setParts) . " WHERE `{$primaryKey}` = ? LIMIT 1");
            if (!$stmt) {
                continue;
            }

            $refs = [];
            $refs[] = $types;
            foreach ($params as $k => $value) {
                $refs[] = &$params[$k];
            }
            call_user_func_array([$stmt, 'bind_param'], $refs);
            $stmt->execute();
            $stmt->close();
            $updated++;
        }
        $res->close();

        return $updated;
    }
}

if (!function_exists('pii_backfill_useraccounts')) {
    function pii_backfill_useraccounts(mysqli $conn): int
    {
        if (!pii_table_exists($conn, 'useraccountstbl')) {
            return 0;
        }

        $res = $conn->query("
            SELECT user_id, email, phone_number, email_lookup_hash, phone_lookup_hash
            FROM useraccountstbl
        ");
        if (!($res instanceof mysqli_result)) {
            return 0;
        }

        $updated = 0;
        while ($row = $res->fetch_assoc()) {
            $plainEmail = pii_decrypt_string((string)($row['email'] ?? ''));
            $plainPhone = pii_decrypt_string((string)($row['phone_number'] ?? ''));
            $prepared = pii_prepare_useraccount_contacts($plainEmail, $plainPhone);

            $needsUpdate = false;
            foreach (['email', 'phone_number', 'email_lookup_hash', 'phone_lookup_hash'] as $field) {
                if (($prepared[$field] ?? null) !== ($row[$field] ?? null)) {
                    $needsUpdate = true;
                    break;
                }
            }
            if (!$needsUpdate) {
                continue;
            }

            $stmt = $conn->prepare("
                UPDATE useraccountstbl
                SET phone_number = ?,
                    phone_lookup_hash = ?,
                    email = ?,
                    email_lookup_hash = ?
                WHERE user_id = ?
                LIMIT 1
            ");
            if (!$stmt) {
                continue;
            }
            $stmt->bind_param(
                'sssss',
                $prepared['phone_number'],
                $prepared['phone_lookup_hash'],
                $prepared['email'],
                $prepared['email_lookup_hash'],
                $row['user_id']
            );
            $stmt->execute();
            $stmt->close();
            $updated++;
        }
        $res->close();

        return $updated;
    }
}

if (!function_exists('pii_backfill_official_invites')) {
    function pii_backfill_official_invites(mysqli $conn): int
    {
        if (!pii_table_exists($conn, 'officialinvitetbl')) {
            return 0;
        }

        $res = $conn->query("
            SELECT invite_id, invite_email, invite_phone, firstname, middlename, lastname, suffix, invite_email_lookup_hash, invite_phone_lookup_hash
            FROM officialinvitetbl
        ");
        if (!($res instanceof mysqli_result)) {
            return 0;
        }

        $updated = 0;
        while ($row = $res->fetch_assoc()) {
            $inviteContact = pii_prepare_official_invite_contacts(
                pii_decrypt_string((string)($row['invite_email'] ?? '')),
                pii_decrypt_string((string)($row['invite_phone'] ?? ''))
            );
            $nameFields = pii_encrypt_field_map([
                'firstname' => pii_decrypt_string((string)($row['firstname'] ?? '')),
                'middlename' => pii_decrypt_string((string)($row['middlename'] ?? '')),
                'lastname' => pii_decrypt_string((string)($row['lastname'] ?? '')),
                'suffix' => pii_decrypt_string((string)($row['suffix'] ?? '')),
            ]);

            $candidate = array_merge($inviteContact, $nameFields);
            $needsUpdate = false;
            foreach (array_keys($candidate) as $field) {
                if (($candidate[$field] ?? null) !== ($row[$field] ?? null)) {
                    $needsUpdate = true;
                    break;
                }
            }
            if (!$needsUpdate) {
                continue;
            }

            $stmt = $conn->prepare("
                UPDATE officialinvitetbl
                SET invite_email = ?,
                    invite_email_lookup_hash = ?,
                    invite_phone = ?,
                    invite_phone_lookup_hash = ?,
                    firstname = ?,
                    middlename = ?,
                    lastname = ?,
                    suffix = ?
                WHERE invite_id = ?
                LIMIT 1
            ");
            if (!$stmt) {
                continue;
            }
            $inviteId = (int)($row['invite_id'] ?? 0);
            $stmt->bind_param(
                'ssssssssi',
                $candidate['invite_email'],
                $candidate['invite_email_lookup_hash'],
                $candidate['invite_phone'],
                $candidate['invite_phone_lookup_hash'],
                $candidate['firstname'],
                $candidate['middlename'],
                $candidate['lastname'],
                $candidate['suffix'],
                $inviteId
            );
            $stmt->execute();
            $stmt->close();
            $updated++;
        }
        $res->close();

        return $updated;
    }
}

if (!function_exists('pii_backfill_core_rows')) {
    function pii_backfill_core_rows(mysqli $conn): array
    {
        if (!pii_is_enabled()) {
            return [];
        }

        pii_ensure_core_schema($conn);

        return [
            'useraccountstbl' => pii_backfill_useraccounts($conn),
            'residentinformationtbl' => pii_backfill_simple_table($conn, 'residentinformationtbl', 'resident_id', pii_resident_fields()),
            'residentaddresstbl' => pii_backfill_simple_table($conn, 'residentaddresstbl', 'address_id', pii_resident_address_fields()),
            'officialinformationtbl' => pii_backfill_simple_table($conn, 'officialinformationtbl', 'official_id', pii_official_fields()),
            'emergencycontacttbl' => pii_backfill_simple_table($conn, 'emergencycontacttbl', 'emergency_id', pii_emergency_contact_fields()),
            'officialinvitetbl' => pii_backfill_official_invites($conn),
        ];
    }
}
