<?php
declare(strict_types=1);

require_once __DIR__ . '/../General/security.php';
require_once __DIR__ . '/../General/connection.php';
require_once __DIR__ . '/../General/audit.php';
require_once __DIR__ . '/auditLogsSupport.php';

requireRoleSession(['SuperAdmin']);

function audit_logs_json_response(int $statusCode, array $payload): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

function audit_logs_bool_input(mixed $value): bool
{
    return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
}

function audit_logs_export_limit(string $format, bool $includeDetails): int
{
    if ($format === 'pdf') {
        return $includeDetails ? AUDIT_LOGS_PDF_DETAILS_ROW_LIMIT : AUDIT_LOGS_PDF_ROW_LIMIT;
    }
    return $includeDetails ? AUDIT_LOGS_CSV_DETAILS_ROW_LIMIT : AUDIT_LOGS_CSV_ROW_LIMIT;
}

function audit_logs_person_label_from_rows(array $rows, array $filters): string
{
    $personUserId = (string)($filters['person_user_id'] ?? '');
    if ($personUserId === '') {
        return '';
    }

    foreach ($rows as $row) {
        if ((string)($row['user_id'] ?? '') !== $personUserId) {
            continue;
        }
        $name = trim((string)($row['display_name'] ?? ''));
        return $name !== '' ? $name . ' (' . $personUserId . ')' : $personUserId;
    }
    return $personUserId;
}

function audit_logs_record_export(mysqli $conn, string $format, array $filters, bool $includeDetails, int $rowCount): void
{
    $actorUserId = trim((string)($_SESSION['user_id'] ?? ''));
    $actorRole = trim((string)($_SESSION['role'] ?? 'SuperAdmin')) ?: 'SuperAdmin';
    $remarks = json_encode([
        'date_from' => $filters['date_from'],
        'date_to' => $filters['date_to'],
        'person_user_id' => $filters['person_user_id'],
        'search_applied' => $filters['q'] !== '',
        'include_details' => $includeDetails,
        'row_count' => $rowCount,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    try {
        insertUnifiedAuditLog(
            $conn,
            $actorUserId !== '' ? $actorUserId : null,
            $actorRole,
            'Audit Logs',
            'audit_log_export',
            $format,
            $format === 'pdf' ? 'Export PDF' : 'Export CSV',
            'exported_rows',
            null,
            (string)$rowCount,
            $remarks !== false ? $remarks : null
        );
    } catch (Throwable $error) {
        error_log('Unable to record the Audit Logs export: ' . $error->getMessage());
    }
}

function audit_logs_send_download_headers(string $contentType, string $filename, ?int $contentLength = null): void
{
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('X-Content-Type-Options: nosniff');
    if ($contentLength !== null && $contentLength >= 0) {
        header('Content-Length: ' . $contentLength);
    }
}

try {
    auditEnsureTable($conn);

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['fetch_audit_people'])) {
        $people = audit_logs_fetch_people($conn);
        audit_logs_json_response(200, [
            'success' => true,
            'data' => $people,
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['fetch_audit_logs'])) {
        $filters = audit_logs_parse_filters($_GET);
        $limit = (int)($_GET['limit'] ?? 200);
        if ($limit <= 0) {
            $limit = 200;
        }
        $limit = min(AUDIT_LOGS_UI_ROW_LIMIT, $limit);
        $result = audit_logs_fetch_consistent_snapshot($conn, $filters, $limit, AUDIT_LOGS_UI_SCAN_LIMIT);

        audit_logs_json_response(200, [
            'success' => true,
            'data' => $result['rows'],
            'meta' => [
                'limit' => $limit,
                'truncated' => $result['truncated'],
                'scan_truncated' => $result['scan_truncated'],
                'scanned_rows' => $result['scanned_rows'],
            ],
        ]);
    }

    if (isset($_POST['export']) || isset($_GET['export'])) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            audit_logs_json_response(405, [
                'success' => false,
                'message' => 'Audit-log exports must use POST.',
            ]);
        }

        verifyCsrfToken(true);
        $format = strtolower(audit_logs_input_text($_POST['export'] ?? '', 'Export format'));
        if (!in_array($format, ['csv', 'pdf'], true)) {
            throw new InvalidArgumentException('Choose either CSV or PDF format.');
        }

        $filters = audit_logs_parse_filters($_POST);
        $includeDetails = audit_logs_bool_input($_POST['include_details'] ?? false);
        $rowLimit = audit_logs_export_limit($format, $includeDetails);
        $result = audit_logs_fetch_consistent_snapshot($conn, $filters, $rowLimit, AUDIT_LOGS_EXPORT_SCAN_LIMIT);

        if ($result['truncated'] || $result['scan_truncated']) {
            $detailNote = $includeDetails ? ' with change details' : '';
            throw new InvalidArgumentException(
                'Too many records match this ' . strtoupper($format) . $detailNote
                . ' export. Narrow the date, person, or search filters and try again.'
            );
        }

        $rows = $result['rows'];
        $columns = audit_logs_export_columns($includeDetails);
        $filename = audit_logs_export_filename($format, $filters);

        if ($format === 'csv') {
            $stream = audit_logs_build_csv_stream($rows, $columns);
            $streamStats = fstat($stream);
            $contentLength = is_array($streamStats) ? (int)($streamStats['size'] ?? 0) : null;

            // The export event is written after the snapshot is generated, so
            // it cannot appear inside the file that triggered it.
            audit_logs_record_export($conn, $format, $filters, $includeDetails, count($rows));
            audit_logs_send_download_headers('text/csv; charset=UTF-8', $filename, $contentLength);
            fpassthru($stream);
            fclose($stream);
            exit;
        }

        require_once __DIR__ . '/auditLogsPdf.php';
        $actorUserId = trim((string)($_SESSION['user_id'] ?? ''));
        $actorRole = trim((string)($_SESSION['role'] ?? 'SuperAdmin')) ?: 'SuperAdmin';
        $generatedBy = $actorUserId !== ''
            ? $actorUserId . ' (' . $actorRole . ')'
            : $actorRole;
        $personLabel = audit_logs_person_label_from_rows($rows, $filters);
        $pdfBytes = audit_logs_build_pdf(
            $rows,
            $columns,
            audit_logs_filter_summary($filters, $personLabel),
            $generatedBy
        );

        audit_logs_record_export($conn, $format, $filters, $includeDetails, count($rows));
        audit_logs_send_download_headers('application/pdf', $filename, strlen($pdfBytes));
        echo $pdfBytes;
        exit;
    }

    audit_logs_json_response(404, [
        'success' => false,
        'message' => 'Not found.',
    ]);
} catch (InvalidArgumentException $error) {
    audit_logs_json_response(422, [
        'success' => false,
        'message' => $error->getMessage(),
    ]);
} catch (Throwable $error) {
    error_log('Audit Logs endpoint failed: ' . $error->getMessage());
    audit_logs_json_response(500, [
        'success' => false,
        'message' => 'Unable to process the audit-log request right now.',
    ]);
}
