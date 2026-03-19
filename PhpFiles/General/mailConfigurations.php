<?php
declare(strict_types=1);

require_once __DIR__ . '/runtimeConfig.php';

$mailHost = trim((string)runtime_env('MAIL_HOST', runtime_config('mail.host', 'smtp.hostinger.com')));
$mailUsername = trim((string)runtime_env('MAIL_USERNAME', runtime_env('MAIL_USER', runtime_config('mail.username', ''))));
$mailPassword = (string)runtime_env('MAIL_PASSWORD', runtime_env('MAIL_PASS', runtime_config('mail.password', '')));
$mailPort = (int)runtime_env('MAIL_PORT', runtime_config('mail.port', 465));
$mailSecure = trim((string)runtime_env('MAIL_SECURE', runtime_config('mail.secure', $mailPort === 587 ? 'tls' : 'ssl')));
$mailSmtpAuth = runtime_bool(runtime_env('MAIL_SMTP_AUTH', runtime_config('mail.smtp_auth', true)), true);
$mailTimeout = (int)runtime_env('MAIL_TIMEOUT', runtime_config('mail.timeout', 30));
$mailSmtpOptions = runtime_config('mail.smtp_options', []);
if (!is_array($mailSmtpOptions)) {
    $mailSmtpOptions = [];
}

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
    'senders' => array_replace_recursive($defaultSenders, $configuredSenders),
];
