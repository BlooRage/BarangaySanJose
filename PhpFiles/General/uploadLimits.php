<?php
declare(strict_types=1);

const APP_UPLOAD_LIMIT_BYTES_RESIDENT = 25 * 1024 * 1024;
const APP_UPLOAD_LIMIT_BYTES_ADMIN = 50 * 1024 * 1024;

function app_upload_actor_key(string $actorType): string
{
    $normalized = strtolower(trim($actorType));
    return $normalized === 'admin' ? 'admin' : 'resident';
}

function app_upload_limit_bytes(string $actorType): int
{
    return app_upload_actor_key($actorType) === 'admin'
        ? APP_UPLOAD_LIMIT_BYTES_ADMIN
        : APP_UPLOAD_LIMIT_BYTES_RESIDENT;
}

function app_upload_limit_label(string $actorType): string
{
    return app_upload_actor_key($actorType) === 'admin' ? '50MB' : '25MB';
}

function app_upload_limit_error(string $actorType, string $label = 'File'): string
{
    $label = trim($label);
    if ($label === '') {
        $label = 'File';
    }

    return sprintf('%s must be %s or less.', $label, app_upload_limit_label($actorType));
}

function app_upload_size_bytes(array $file): int
{
    $size = (int)($file['size'] ?? 0);
    if ($size > 0) {
        return $size;
    }

    $tmpName = (string)($file['tmp_name'] ?? '');
    if ($tmpName !== '' && is_file($tmpName)) {
        $tmpSize = @filesize($tmpName);
        if ($tmpSize !== false && (int)$tmpSize > 0) {
            return (int)$tmpSize;
        }
    }

    return 0;
}

function app_upload_error_message(int $errorCode, string $actorType, string $label = 'File', bool $required = false): ?string
{
    $label = trim($label);
    if ($label === '') {
        $label = 'File';
    }

    return match ($errorCode) {
        UPLOAD_ERR_OK => null,
        UPLOAD_ERR_NO_FILE => $required ? sprintf('%s is required.', $label) : null,
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => app_upload_limit_error($actorType, $label),
        UPLOAD_ERR_PARTIAL => sprintf('%s upload was interrupted. Please try again.', $label),
        UPLOAD_ERR_NO_TMP_DIR => 'Server upload temp directory is missing.',
        UPLOAD_ERR_CANT_WRITE => sprintf('Server could not write the uploaded %s.', strtolower($label)),
        UPLOAD_ERR_EXTENSION => sprintf('A server extension blocked the %s upload.', strtolower($label)),
        default => sprintf('%s upload failed.', $label),
    };
}

function app_upload_validate_file(array $file, string $actorType, string $label = 'File', bool $required = false): ?string
{
    $errorCode = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    $errorMessage = app_upload_error_message($errorCode, $actorType, $label, $required);
    if ($errorMessage !== null) {
        return $errorMessage;
    }

    if ($errorCode !== UPLOAD_ERR_OK) {
        return null;
    }

    $size = app_upload_size_bytes($file);
    if ($size <= 0) {
        return sprintf('%s is empty.', trim($label) !== '' ? trim($label) : 'File');
    }

    if ($size > app_upload_limit_bytes($actorType)) {
        return app_upload_limit_error($actorType, $label);
    }

    return null;
}
