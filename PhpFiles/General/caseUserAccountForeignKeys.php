<?php

if (!function_exists('cuafk_is_valid_identifier')) {
    function cuafk_is_valid_identifier(string $value): bool
    {
        return preg_match('/^[A-Za-z0-9_]+$/', $value) === 1;
    }
}

if (!function_exists('cuafk_escape_identifier')) {
    function cuafk_escape_identifier(string $value): string
    {
        if (!cuafk_is_valid_identifier($value)) {
            throw new InvalidArgumentException("Invalid SQL identifier: {$value}");
        }

        return '`' . $value . '`';
    }
}

if (!function_exists('cuafk_table_exists')) {
    function cuafk_table_exists(mysqli $conn, string $tableName): bool
    {
        $stmt = $conn->prepare("
            SELECT 1
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
            LIMIT 1
        ");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('s', $tableName);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_row();
        $stmt->close();

        return !empty($row);
    }
}

if (!function_exists('cuafk_column_exists')) {
    function cuafk_column_exists(mysqli $conn, string $tableName, string $columnName): bool
    {
        $stmt = $conn->prepare("
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
            LIMIT 1
        ");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('ss', $tableName, $columnName);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_row();
        $stmt->close();

        return !empty($row);
    }
}

if (!function_exists('cuafk_get_column_foreign_keys')) {
    function cuafk_get_column_foreign_keys(mysqli $conn, string $tableName, string $columnName): array
    {
        $stmt = $conn->prepare("
            SELECT
                kcu.CONSTRAINT_NAME,
                kcu.REFERENCED_TABLE_NAME,
                kcu.REFERENCED_COLUMN_NAME,
                rc.DELETE_RULE,
                rc.UPDATE_RULE
            FROM information_schema.KEY_COLUMN_USAGE kcu
            LEFT JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
              ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
             AND rc.TABLE_NAME = kcu.TABLE_NAME
             AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
            WHERE kcu.TABLE_SCHEMA = DATABASE()
              AND kcu.TABLE_NAME = ?
              AND kcu.COLUMN_NAME = ?
              AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
            ORDER BY kcu.CONSTRAINT_NAME ASC
        ");
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('ss', $tableName, $columnName);
        $stmt->execute();
        $result = $stmt->get_result();
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = [
                'constraint_name' => (string)($row['CONSTRAINT_NAME'] ?? ''),
                'referenced_table_name' => (string)($row['REFERENCED_TABLE_NAME'] ?? ''),
                'referenced_column_name' => (string)($row['REFERENCED_COLUMN_NAME'] ?? ''),
                'delete_rule' => strtoupper(trim((string)($row['DELETE_RULE'] ?? ''))),
                'update_rule' => strtoupper(trim((string)($row['UPDATE_RULE'] ?? ''))),
            ];
        }
        $stmt->close();

        return $items;
    }
}

if (!function_exists('cuafk_drop_foreign_keys')) {
    function cuafk_drop_foreign_keys(mysqli $conn, string $tableName, array $foreignKeys): void
    {
        $tableSql = cuafk_escape_identifier($tableName);
        $names = [];

        foreach ($foreignKeys as $foreignKey) {
            $constraintName = trim((string)($foreignKey['constraint_name'] ?? ''));
            if ($constraintName === '') {
                continue;
            }
            $names[$constraintName] = true;
        }

        foreach (array_keys($names) as $constraintName) {
            $constraintSql = cuafk_escape_identifier($constraintName);
            if (!$conn->query("ALTER TABLE {$tableSql} DROP FOREIGN KEY {$constraintSql}")) {
                throw new RuntimeException("Failed to drop foreign key {$constraintName} on {$tableName}: {$conn->error}");
            }
        }
    }
}

if (!function_exists('cuafk_foreign_key_matches_spec')) {
    function cuafk_foreign_key_matches_spec(array $foreignKey, array $spec): bool
    {
        return strtolower((string)($foreignKey['referenced_table_name'] ?? '')) === strtolower((string)($spec['referenced_table'] ?? ''))
            && strtolower((string)($foreignKey['referenced_column_name'] ?? '')) === strtolower((string)($spec['referenced_column'] ?? ''))
            && strtoupper((string)($foreignKey['delete_rule'] ?? '')) === strtoupper((string)($spec['delete_rule'] ?? ''))
            && strtoupper((string)($foreignKey['update_rule'] ?? '')) === strtoupper((string)($spec['update_rule'] ?? ''));
    }
}

if (!function_exists('cuafk_ensure_foreign_key')) {
    function cuafk_ensure_foreign_key(mysqli $conn, array $spec, bool $strict = false): void
    {
        $tableName = (string)($spec['table'] ?? '');
        $columnName = (string)($spec['column'] ?? '');
        $constraintName = (string)($spec['constraint'] ?? '');
        $referencedTable = (string)($spec['referenced_table'] ?? 'useraccountstbl');
        $referencedColumn = (string)($spec['referenced_column'] ?? 'user_id');
        $deleteRule = strtoupper((string)($spec['delete_rule'] ?? 'SET NULL'));
        $updateRule = strtoupper((string)($spec['update_rule'] ?? 'CASCADE'));

        try {
            foreach ([$tableName, $columnName, $constraintName, $referencedTable, $referencedColumn] as $identifier) {
                cuafk_escape_identifier($identifier);
            }

            if (!cuafk_table_exists($conn, $tableName) || !cuafk_column_exists($conn, $tableName, $columnName)) {
                return;
            }

            $currentKeys = cuafk_get_column_foreign_keys($conn, $tableName, $columnName);
            if (count($currentKeys) === 1 && cuafk_foreign_key_matches_spec($currentKeys[0], [
                'referenced_table' => $referencedTable,
                'referenced_column' => $referencedColumn,
                'delete_rule' => $deleteRule,
                'update_rule' => $updateRule,
            ])) {
                return;
            }

            if ($currentKeys !== []) {
                cuafk_drop_foreign_keys($conn, $tableName, $currentKeys);
            }

            $tableSql = cuafk_escape_identifier($tableName);
            $columnSql = cuafk_escape_identifier($columnName);
            $constraintSql = cuafk_escape_identifier($constraintName);
            $referencedTableSql = cuafk_escape_identifier($referencedTable);
            $referencedColumnSql = cuafk_escape_identifier($referencedColumn);

            $sql = "
                ALTER TABLE {$tableSql}
                ADD CONSTRAINT {$constraintSql}
                FOREIGN KEY ({$columnSql})
                REFERENCES {$referencedTableSql}({$referencedColumnSql})
                ON DELETE {$deleteRule}
                ON UPDATE {$updateRule}
            ";

            if (!$conn->query($sql)) {
                throw new RuntimeException("Failed to add foreign key {$constraintName} on {$tableName}.{$columnName}: {$conn->error}");
            }
        } catch (Throwable $e) {
            if ($strict) {
                throw $e;
            }
            error_log('cuafk_ensure_foreign_key failed: ' . $e->getMessage());
        }
    }
}

if (!function_exists('cuafk_ensure_case_useraccount_foreign_keys')) {
    function cuafk_ensure_case_useraccount_foreign_keys(mysqli $conn, bool $strict = false): void
    {
        static $done = false;

        if ($done) {
            return;
        }
        $done = true;

        $specs = [
            [
                'table' => 'casereportstbl',
                'column' => 'resident_user_id',
                'constraint' => 'fk_case_resident_user',
                'delete_rule' => 'SET NULL',
                'update_rule' => 'CASCADE',
            ],
            [
                'table' => 'casereportstbl',
                'column' => 'user_id_official_record_by',
                'constraint' => 'fk_case_recorded_by',
                'delete_rule' => 'SET NULL',
                'update_rule' => 'CASCADE',
            ],
            [
                'table' => 'casereportstbl',
                'column' => 'user_id_official_reviewed_by',
                'constraint' => 'fk_case_reviewed_by',
                'delete_rule' => 'SET NULL',
                'update_rule' => 'CASCADE',
            ],
            [
                'table' => 'casereportstbl',
                'column' => 'user_id_official_update_by',
                'constraint' => 'fk_case_updated_by',
                'delete_rule' => 'SET NULL',
                'update_rule' => 'CASCADE',
            ],
            [
                'table' => 'casesignaturestbl',
                'column' => 'captured_by_user_id',
                'constraint' => 'fk_casesignature_captured_by',
                'delete_rule' => 'SET NULL',
                'update_rule' => 'CASCADE',
            ],
            [
                'table' => 'casestatushistorytbl',
                'column' => 'changed_by',
                'constraint' => 'fk_casehistory_changed_by',
                'delete_rule' => 'SET NULL',
                'update_rule' => 'CASCADE',
            ],
            [
                'table' => 'caseupdateslogtbl',
                'column' => 'logged_by_user_id',
                'constraint' => 'fk_caseupdateslog_logged_by',
                'delete_rule' => 'SET NULL',
                'update_rule' => 'CASCADE',
            ],
            [
                'table' => 'blotterrequeststbl',
                'column' => 'recommended_by_user_id',
                'constraint' => 'fk_blotterrequest_recommended_by',
                'delete_rule' => 'SET NULL',
                'update_rule' => 'CASCADE',
            ],
            [
                'table' => 'blotterrequeststbl',
                'column' => 'reviewed_by_user_id',
                'constraint' => 'fk_blotterrequest_reviewed_by',
                'delete_rule' => 'SET NULL',
                'update_rule' => 'CASCADE',
            ],
            [
                'table' => 'complaintstbl',
                'column' => 'escalated_by_user_id',
                'constraint' => 'fk_complaint_escalated_by',
                'delete_rule' => 'SET NULL',
                'update_rule' => 'CASCADE',
            ],
        ];

        foreach ($specs as $spec) {
            cuafk_ensure_foreign_key($conn, $spec, $strict);
        }
    }
}
