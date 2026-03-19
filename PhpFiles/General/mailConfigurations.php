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

$mailHost = trim((string)runtime_env('MAIL_HOST', runtime_config('mail.host', 'smtp.hostinger.com')));
$mailUsername = trim((string)runtime_env('MAIL_USERNAME', runtime_env('MAIL_USER', runtime_config('mail.username', ''))));
$mailPassword = (string)runtime_env('MAIL_PASSWORD', runtime_env('MAIL_PASS', runtime_config('mail.password', '')));
$mailPort = (int)runtime_env('MAIL_PORT', runtime_config('mail.port', 465));
$mailSecure = trim((string)runtime_env('MAIL_SECURE', runtime_config('mail.secure', $mailPort === 587 ? 'tls' : 'ssl')));
$mailSmtpAuth = runtime_bool(runtime_env('MAIL_SMTP_AUTH', runtime_config('mail.smtp_auth', true)), true);

$mailFromEmail = trim((string)runtime_env('MAIL_FROM_EMAIL', runtime_config('mail.from_email', $mailUsername)));
$mailFromName = trim((string)runtime_env('MAIL_FROM_NAME', runtime_config('mail.from_name', 'Barangay San Jose')));

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
    'from_email' => $mailFromEmail,
    'from_name' => $mailFromName,
    'senders' => $resolvedSenders,
];
