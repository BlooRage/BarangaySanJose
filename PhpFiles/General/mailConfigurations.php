<?php
declare(strict_types=1);

require_once __DIR__ . '/runtimeConfig.php';

if (!function_exists('mail_config_value')) {
    function mail_config_value(array $config, string $key, $fallback = '')
    {
        if (!array_key_exists($key, $config)) {
            return $fallback;
        }

        $value = $config[$key];
        if (is_string($value)) {
            $value = trim($value);
            return $value !== '' ? $value : $fallback;
        }

        return $value ?? $fallback;
    }
}

// Mail credentials must come from environment variables or runtime config.
$defaultMailHost = '';
$defaultMailUsername = '';
$defaultMailPassword = '';
$defaultMailPort = 465;
$defaultMailFromEmail = '';
$defaultMailFromName = 'Barangay San Jose';

$mailHost = trim((string)runtime_env('MAIL_HOST', runtime_config('mail.host', $defaultMailHost)));
$mailUsername = trim((string)runtime_env('MAIL_USERNAME', runtime_env('MAIL_USER', runtime_config('mail.username', $defaultMailUsername))));
$mailPassword = (string)runtime_env('MAIL_PASSWORD', runtime_env('MAIL_PASS', runtime_config('mail.password', $defaultMailPassword)));
$mailPort = (int)runtime_env('MAIL_PORT', runtime_config('mail.port', $defaultMailPort));
$mailSecure = trim((string)runtime_env('MAIL_SECURE', runtime_config('mail.secure', $mailPort === 587 ? 'tls' : 'ssl')));
$mailSmtpAuth = runtime_bool(runtime_env('MAIL_SMTP_AUTH', runtime_config('mail.smtp_auth', true)), true);
$mailTimeout = (int)runtime_env('MAIL_TIMEOUT', runtime_config('mail.timeout', 30));
$mailSmtpOptions = runtime_config('mail.smtp_options', []);
if (!is_array($mailSmtpOptions)) {
    $mailSmtpOptions = [];
}

$mailFromEmail = trim((string)runtime_env('MAIL_FROM_EMAIL', runtime_config('mail.from_email', $mailUsername !== '' ? $mailUsername : $defaultMailFromEmail)));
$mailFromName = trim((string)runtime_env('MAIL_FROM_NAME', runtime_config('mail.from_name', $defaultMailFromName)));

$defaultSenders = [
    'verify' => [
        'from_email' => $mailFromEmail,
        'from_name' => 'Barangay San Jose Verification',
    ],
    'one_time' => [
        'from_email' => $mailFromEmail,
        'from_name' => 'Barangay San Jose Access',
    ],
    'onboarding_access' => [
        'from_email' => $mailFromEmail,
        'from_name' => 'Barangay San Jose',
    ],
    'announcement' => [
        'from_email' => $mailFromEmail,
        'from_name' => 'Barangay San Jose Announcements',
    ],
    'transaction' => [
        'from_email' => $mailFromEmail,
        'from_name' => 'Barangay San Jose Notifications',
    ],
];

$configuredSenders = runtime_config('mail.senders', []);
if (!is_array($configuredSenders)) {
    $configuredSenders = [];
}

$resolvedSenders = $defaultSenders;
foreach ($configuredSenders as $senderType => $senderConfig) {
    if (!is_array($senderConfig)) {
        continue;
    }

    $resolvedSenders[$senderType] = [
        'from_email' => mail_config_value(
            $senderConfig,
            'from_email',
            (string)($defaultSenders[$senderType]['from_email'] ?? $mailFromEmail)
        ),
        'from_name' => mail_config_value(
            $senderConfig,
            'from_name',
            (string)($defaultSenders[$senderType]['from_name'] ?? $mailFromName)
        ),
    ];
}

return [
    'host' => $mailHost,
    'username' => $mailUsername,
    'password' => $mailPassword,
    'port' => $mailPort,
    'secure' => $mailSecure,
    'smtp_auth' => $mailSmtpAuth,
    'timeout' => $mailTimeout > 0 ? $mailTimeout : 30,
    'smtp_options' => $mailSmtpOptions,
    'from_email' => $mailFromEmail,
    'from_name' => $mailFromName,
    'senders' => $resolvedSenders,
];
