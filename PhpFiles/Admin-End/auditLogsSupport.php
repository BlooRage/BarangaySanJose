<?php
declare(strict_types=1);

require_once __DIR__ . '/../General/piiCrypto.php';

const AUDIT_LOGS_UI_ROW_LIMIT = 500;
const AUDIT_LOGS_UI_SCAN_LIMIT = 50000;
const AUDIT_LOGS_EXPORT_SCAN_LIMIT = 100000;
const AUDIT_LOGS_CSV_ROW_LIMIT = 10000;
const AUDIT_LOGS_CSV_DETAILS_ROW_LIMIT = 5000;
const AUDIT_LOGS_PDF_ROW_LIMIT = 2000;
const AUDIT_LOGS_PDF_DETAILS_ROW_LIMIT = 500;

function audit_logs_input_text(mixed $value, string $label): string
{
    if (is_array($value) || is_object($value) || is_resource($value)) {
        throw new InvalidArgumentException($label . ' is invalid.');
    }
    return trim((string)$value);
}

function audit_logs_format_name(mixed $firstName, mixed $middleName, mixed $lastName, mixed $suffix): string
{
    $firstName = trim((string)$firstName);
    $middleName = trim((string)$middleName);
    $lastName = trim((string)$lastName);
    $suffix = trim((string)$suffix);

    if ($firstName === '' && $lastName === '') {
        return '';
    }

    $middleInitial = '';
    if ($middleName !== '') {
        $middleInitial = function_exists('mb_substr')
            ? mb_substr($middleName, 0, 1, 'UTF-8')
            : substr($middleName, 0, 1);
        $middleInitial .= '. ';
    }

    $name = trim($firstName . ' ' . $middleInitial . $lastName);
    return trim($name . ($suffix !== '' ? ' ' . $suffix : ''));
}

function audit_logs_decrypt_row(array $row): array
{
    $row = pii_decrypt_assoc($row, [
        'o_firstname',
        'o_middlename',
        'o_lastname',
        'o_suffix',
        'r_firstname',
        'r_middlename',
        'r_lastname',
        'r_suffix',
        'old_value',
        'new_value',
        'remarks',
    ]);

    $officialName = audit_logs_format_name(
        $row['o_firstname'] ?? '',
        $row['o_middlename'] ?? '',
        $row['o_lastname'] ?? '',
        $row['o_suffix'] ?? ''
    );
    $residentName = audit_logs_format_name(
        $row['r_firstname'] ?? '',
        $row['r_middlename'] ?? '',
        $row['r_lastname'] ?? '',
        $row['r_suffix'] ?? ''
    );

    $row['display_name'] = $officialName !== '' ? $officialName : $residentName;
    unset(
        $row['o_firstname'],
        $row['o_middlename'],
        $row['o_lastname'],
        $row['o_suffix'],
        $row['r_firstname'],
        $row['r_middlename'],
        $row['r_lastname'],
        $row['r_suffix']
    );

    return $row;
}

function audit_logs_matches_search(array $row, string $needle): bool
{
    return pii_search_match($row, [
        'audit_id',
        'user_id',
        'display_name',
        'role_access',
        'module_affected',
        'target_type',
        'target_id',
        'action_type',
        'field_changed',
        'old_value',
        'new_value',
        'remarks',
        'action_timestamp',
    ], $needle);
}

function audit_logs_strict_date(mixed $value, string $label): string
{
    $value = audit_logs_input_text($value, $label);
    if ($value === '') {
        return '';
    }

    $timezone = new DateTimeZone('Asia/Manila');
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);
    $errors = DateTimeImmutable::getLastErrors();
    $hasErrors = is_array($errors)
        && (((int)($errors['warning_count'] ?? 0) > 0) || ((int)($errors['error_count'] ?? 0) > 0));

    if (!$date || $hasErrors || $date->format('Y-m-d') !== $value) {
        throw new InvalidArgumentException($label . ' must be a valid date.');
    }

    return $value;
}

function audit_logs_parse_filters(array $input): array
{
    $query = audit_logs_input_text($input['q'] ?? '', 'Search');
    if (function_exists('mb_substr')) {
        $query = mb_substr($query, 0, 200, 'UTF-8');
    } else {
        $query = substr($query, 0, 200);
    }

    $dateFrom = audit_logs_strict_date($input['date_from'] ?? $input['from'] ?? '', 'Date From');
    $dateTo = audit_logs_strict_date($input['date_to'] ?? $input['to'] ?? '', 'Date To');
    if ($dateFrom !== '' && $dateTo !== '' && strcmp($dateFrom, $dateTo) > 0) {
        throw new InvalidArgumentException('Date From cannot be later than Date To.');
    }

    $personUserId = audit_logs_input_text($input['person_user_id'] ?? '', 'The selected person');
    if (strlen($personUserId) > 64) {
        throw new InvalidArgumentException('The selected person is invalid.');
    }

    return [
        'q' => $query,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'person_user_id' => $personUserId,
    ];
}

function audit_logs_bind_params(mysqli_stmt $stmt, string $types, array &$params): void
{
    if ($types === '') {
        return;
    }

    $references = [];
    $references[] = &$types;
    foreach ($params as $index => &$value) {
        $references[] = &$value;
    }
    unset($value);
    call_user_func_array([$stmt, 'bind_param'], $references);
}

function audit_logs_where_clause(array $filters): array
{
    $clauses = [];
    $types = '';
    $params = [];

    if ($filters['date_from'] !== '') {
        $clauses[] = 'a.action_timestamp >= ?';
        $types .= 's';
        $params[] = $filters['date_from'] . ' 00:00:00';
    }

    if ($filters['date_to'] !== '') {
        $timezone = new DateTimeZone('Asia/Manila');
        $exclusiveEnd = (new DateTimeImmutable($filters['date_to'], $timezone))
            ->modify('+1 day')
            ->format('Y-m-d 00:00:00');
        $clauses[] = 'a.action_timestamp < ?';
        $types .= 's';
        $params[] = $exclusiveEnd;
    }

    if ($filters['person_user_id'] !== '') {
        $clauses[] = 'a.user_id = ?';
        $types .= 's';
        $params[] = $filters['person_user_id'];
    }

    return [
        'sql' => $clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses),
        'types' => $types,
        'params' => $params,
    ];
}

function audit_logs_select_sql(string $whereSql): string
{
    return "
        SELECT
            a.audit_id,
            a.user_id,
            a.role_access,
            a.module_affected,
            a.target_type,
            a.target_id,
            a.action_type,
            a.field_changed,
            a.old_value,
            a.new_value,
            a.remarks,
            a.action_timestamp,
            oi.firstname AS o_firstname,
            oi.middlename AS o_middlename,
            oi.lastname AS o_lastname,
            oi.suffix AS o_suffix,
            ri.firstname AS r_firstname,
            ri.middlename AS r_middlename,
            ri.lastname AS r_lastname,
            ri.suffix AS r_suffix
        FROM unifiedauditlogstbl a
        LEFT JOIN officialinformationtbl oi
            ON oi.user_id COLLATE utf8mb4_general_ci = a.user_id COLLATE utf8mb4_general_ci
        LEFT JOIN residentinformationtbl ri
            ON ri.user_id COLLATE utf8mb4_general_ci = a.user_id COLLATE utf8mb4_general_ci
        {$whereSql}
        ORDER BY a.action_timestamp DESC, a.audit_id DESC
        LIMIT ? OFFSET ?
    ";
}

/**
 * Fetch matching rows after applying indexed date/person filters in SQL and
 * decrypted free-text search in PHP. One extra match is collected so callers
 * can reject or disclose truncated results instead of silently omitting rows.
 */
function audit_logs_fetch_matching(
    mysqli $conn,
    array $filters,
    int $maxRows,
    int $maxScannedRows
): array {
    $maxRows = max(1, $maxRows);
    $maxScannedRows = max($maxRows + 1, $maxScannedRows);
    $targetMatchCount = $maxRows + 1;
    $where = audit_logs_where_clause($filters);
    $sql = audit_logs_select_sql($where['sql']);
    $matchedRows = [];
    $offset = 0;
    $scannedRows = 0;
    $exhausted = false;
    $batchSize = 1000;

    while (count($matchedRows) < $targetMatchCount && $scannedRows < $maxScannedRows) {
        $remainingScan = $maxScannedRows - $scannedRows;
        $currentBatchSize = min($batchSize, $remainingScan);
        $params = $where['params'];
        $params[] = $currentBatchSize;
        $params[] = $offset;
        $types = $where['types'] . 'ii';

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Unable to prepare the audit-log query.');
        }
        audit_logs_bind_params($stmt, $types, $params);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Unable to load audit logs.');
        }

        $result = $stmt->get_result();
        if (!($result instanceof mysqli_result)) {
            $stmt->close();
            throw new RuntimeException('Unable to read audit logs.');
        }
        $batchCount = 0;
        while ($row = $result->fetch_assoc()) {
            $batchCount++;
            $row = audit_logs_decrypt_row($row);
            if ($filters['q'] !== '' && !audit_logs_matches_search($row, $filters['q'])) {
                continue;
            }
            $matchedRows[] = $row;
            if (count($matchedRows) >= $targetMatchCount) {
                break;
            }
        }
        $stmt->close();

        $scannedRows += $batchCount;
        $offset += $batchCount;
        if ($batchCount < $currentBatchSize) {
            $exhausted = true;
            break;
        }
    }

    $truncated = count($matchedRows) > $maxRows;
    if ($truncated) {
        $matchedRows = array_slice($matchedRows, 0, $maxRows);
    }

    return [
        'rows' => $matchedRows,
        'truncated' => $truncated,
        'scan_truncated' => !$exhausted && !$truncated && $scannedRows >= $maxScannedRows,
        'scanned_rows' => $scannedRows,
    ];
}

/**
 * Keep every batched SELECT on one repeatable-read snapshot. Audit records can
 * be appended while an export is running; without this wrapper OFFSET-based
 * batches could otherwise repeat or skip evidence as the result set shifts.
 */
function audit_logs_fetch_consistent_snapshot(
    mysqli $conn,
    array $filters,
    int $maxRows,
    int $maxScannedRows
): array {
    $transactionStarted = false;
    try {
        if (!$conn->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ')) {
            throw new RuntimeException('Unable to prepare a consistent audit-log snapshot.');
        }
        $flags = MYSQLI_TRANS_START_READ_ONLY | MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT;
        if (!$conn->begin_transaction($flags)) {
            throw new RuntimeException('Unable to start a consistent audit-log snapshot.');
        }
        $transactionStarted = true;

        $result = audit_logs_fetch_matching($conn, $filters, $maxRows, $maxScannedRows);
        if (!$conn->commit()) {
            throw new RuntimeException('Unable to complete the audit-log snapshot.');
        }
        $transactionStarted = false;
        return $result;
    } catch (Throwable $error) {
        if ($transactionStarted) {
            try {
                $conn->rollback();
            } catch (Throwable) {
                // Preserve the original query error.
            }
        }
        throw $error;
    }
}

function audit_logs_fetch_people(mysqli $conn, int $limit = 2000): array
{
    $limit = max(1, min(2000, $limit));
    $sql = "
        SELECT
            actors.user_id,
            actors.last_activity,
            oi.firstname AS o_firstname,
            oi.middlename AS o_middlename,
            oi.lastname AS o_lastname,
            oi.suffix AS o_suffix,
            ri.firstname AS r_firstname,
            ri.middlename AS r_middlename,
            ri.lastname AS r_lastname,
            ri.suffix AS r_suffix
        FROM (
            SELECT user_id, MAX(action_timestamp) AS last_activity
            FROM unifiedauditlogstbl
            WHERE user_id IS NOT NULL AND TRIM(user_id) <> ''
            GROUP BY user_id
            ORDER BY last_activity DESC
            LIMIT {$limit}
        ) actors
        LEFT JOIN officialinformationtbl oi
            ON oi.user_id COLLATE utf8mb4_general_ci = actors.user_id COLLATE utf8mb4_general_ci
        LEFT JOIN residentinformationtbl ri
            ON ri.user_id COLLATE utf8mb4_general_ci = actors.user_id COLLATE utf8mb4_general_ci
        ORDER BY actors.last_activity DESC, actors.user_id ASC
    ";

    $result = $conn->query($sql);
    if (!($result instanceof mysqli_result)) {
        throw new RuntimeException('Unable to load the audit-log people list.');
    }

    $people = [];
    $seenUserIds = [];
    while ($row = $result->fetch_assoc()) {
        $row = audit_logs_decrypt_row($row);
        $userId = trim((string)($row['user_id'] ?? ''));
        if ($userId === '' || isset($seenUserIds[$userId])) {
            continue;
        }
        $seenUserIds[$userId] = true;
        $name = trim((string)($row['display_name'] ?? ''));
        $people[] = [
            'user_id' => $userId,
            'display_name' => $name,
            'label' => $name !== '' ? $name . ' (' . $userId . ')' : $userId,
            'last_activity' => (string)($row['last_activity'] ?? ''),
        ];
    }

    usort($people, static function (array $left, array $right): int {
        return strnatcasecmp((string)$left['label'], (string)$right['label']);
    });

    return $people;
}

function audit_logs_export_column_catalog(): array
{
    return [
        'audit_id' => 'Audit ID',
        'timestamp' => 'Timestamp',
        'user_id' => 'User ID',
        'name' => 'Name',
        'role_access' => 'Role Access',
        'action_type' => 'Action',
        'module_affected' => 'Module',
        'target' => 'Target',
        'field_changed' => 'Field',
        'old_value' => 'Old Value',
        'new_value' => 'New Value',
        'remarks' => 'Remarks',
    ];
}

function audit_logs_export_columns(bool $includeDetails): array
{
    $columns = [
        'audit_id',
        'timestamp',
        'user_id',
        'name',
        'role_access',
        'action_type',
        'module_affected',
        'target',
        'field_changed',
    ];

    if ($includeDetails) {
        array_push($columns, 'old_value', 'new_value', 'remarks');
    }
    return $columns;
}

function audit_logs_column_value(array $row, string $column): string
{
    return match ($column) {
        'audit_id' => trim((string)($row['audit_id'] ?? '')),
        'timestamp' => trim((string)($row['action_timestamp'] ?? '')),
        'user_id' => trim((string)($row['user_id'] ?? '')),
        'name' => trim((string)($row['display_name'] ?? '')),
        'role_access' => trim((string)($row['role_access'] ?? '')),
        'action_type' => trim((string)($row['action_type'] ?? '')),
        'module_affected' => trim((string)($row['module_affected'] ?? '')),
        'target' => trim(implode(' #', array_filter([
            trim((string)($row['target_type'] ?? '')),
            trim((string)($row['target_id'] ?? '')),
        ], static fn(string $value): bool => $value !== ''))),
        'field_changed' => trim((string)($row['field_changed'] ?? '')),
        'old_value' => trim((string)($row['old_value'] ?? '')),
        'new_value' => trim((string)($row['new_value'] ?? '')),
        'remarks' => trim((string)($row['remarks'] ?? '')),
        default => '',
    };
}

function audit_logs_csv_safe_value(string $value): string
{
    $value = str_replace("\0", '', $value);
    if (
        preg_match('/^[\x00-\x20\p{Z}]*[=+\-@]/u', $value) === 1
        || preg_match('/^[\t\r\n]/u', $value) === 1
    ) {
        return "'" . $value;
    }
    return $value;
}

/** @return resource */
function audit_logs_build_csv_stream(array $rows, array $columns)
{
    $catalog = audit_logs_export_column_catalog();
    $stream = fopen('php://temp/maxmemory:8388608', 'w+b');
    if ($stream === false) {
        throw new RuntimeException('Unable to prepare the CSV export.');
    }

    fwrite($stream, "\xEF\xBB\xBF");
    fputcsv(
        $stream,
        array_map(static fn(string $column): string => $catalog[$column] ?? $column, $columns),
        ',',
        '"',
        ''
    );
    foreach ($rows as $row) {
        $values = [];
        foreach ($columns as $column) {
            $values[] = audit_logs_csv_safe_value(audit_logs_column_value($row, $column));
        }
        fputcsv($stream, $values, ',', '"', '');
    }
    rewind($stream);
    return $stream;
}

function audit_logs_export_filename(string $format, array $filters): string
{
    $parts = ['audit-logs'];
    if ($filters['date_from'] !== '' && $filters['date_to'] !== '') {
        $parts[] = $filters['date_from'] . '_to_' . $filters['date_to'];
    } elseif ($filters['date_from'] !== '') {
        $parts[] = 'from_' . $filters['date_from'];
    } elseif ($filters['date_to'] !== '') {
        $parts[] = 'through_' . $filters['date_to'];
    } else {
        $parts[] = date('Y-m-d_His');
    }

    if ($filters['person_user_id'] !== '') {
        $safePerson = preg_replace('/[^A-Za-z0-9_-]+/', '-', $filters['person_user_id']) ?: 'person';
        $parts[] = $safePerson;
    }

    return implode('_', $parts) . '.' . $format;
}

function audit_logs_filter_summary(array $filters, string $personLabel = ''): array
{
    $cleanSummaryValue = static function (mixed $value): string {
        $value = str_replace("\0", '', (string)$value);
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    };

    $summary = [];
    if ($filters['date_from'] !== '') {
        $summary[] = 'From: ' . $cleanSummaryValue($filters['date_from']);
    }
    if ($filters['date_to'] !== '') {
        $summary[] = 'To: ' . $cleanSummaryValue($filters['date_to']);
    }
    if ($filters['person_user_id'] !== '') {
        $summary[] = 'Performed by: ' . $cleanSummaryValue(
            $personLabel !== '' ? $personLabel : $filters['person_user_id']
        );
    }
    if ($filters['q'] !== '') {
        $summary[] = 'Search: ' . $cleanSummaryValue($filters['q']);
    }
    return $summary === [] ? ['All available audit logs'] : $summary;
}
