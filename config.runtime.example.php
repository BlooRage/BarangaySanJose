<?php
// Copy this to config.runtime.php or config.runtime.local.php on the target server.
// Both runtime files are gitignored, so they must be uploaded/deployed manually.
return [
    'app' => [
        // Example: https://barangaysanjose-montalban.com
        // Example with subfolder: https://example.com/BarangaySanJose
        'base_url' => 'https://your-domain.example',

        // Optional explicit subfolder override, e.g. /BarangaySanJose
        'root_path' => '',

        // Set true when HTTPS is terminated by a proxy/load balancer and
        // forwarded headers are unavailable or unreliable.
        'force_https' => true,
    ],

    'db' => [
        'host' => '127.0.0.1',
        'host_local' => '127.0.0.1',
        'host_hosted' => 'localhost',
        'port' => 3306,
        'user' => 'your_db_user',
        'pass' => 'your_db_password',
        'name' => 'your_db_name',
    ],

    'mail' => [
        'host' => 'smtp.hostinger.com',
        'username' => 'official@your-domain.example',
        'password' => 'your_smtp_password',
        'port' => 465,
        'secure' => 'ssl',
        'smtp_auth' => true,
        'timeout' => 30,
        'from_email' => 'official@your-domain.example',
        'from_name' => 'Barangay San Jose',
        // Optional advanced PHPMailer transport settings if your host needs them.
        // Leave empty unless you are intentionally overriding TLS behavior.
        'smtp_options' => [],
        'senders' => [
            // Optional: only use a different from_email if that mailbox/alias
            // really exists in your mail provider (e.g. Hostinger).
            // Otherwise keep it the same as your authenticated mailbox.
            'verify' => [
                'from_email' => 'official@your-domain.example',
                'from_name' => 'Barangay San Jose Verification',
            ],
            'one_time' => [
                'from_email' => 'official@your-domain.example',
                'from_name' => 'Barangay San Jose Access',
            ],
            'onboarding_access' => [
                'from_email' => 'official@your-domain.example',
                'from_name' => 'Barangay San Jose',
            ],
            'announcement' => [
                'from_email' => 'official@your-domain.example',
                'from_name' => 'Barangay San Jose Announcements',
            ],
            'transaction' => [
                'from_email' => 'official@your-domain.example',
                'from_name' => 'Barangay San Jose Notifications',
            ],
        ],
    ],

    'sms' => [
        'semaphore_api_key' => 'your_semaphore_api_key',
        'sender' => 'BrgySanJose',
        'endpoint' => 'https://api.semaphore.co/api/v4/messages',
        'otp_endpoint' => 'https://api.semaphore.co/api/v4/otp',
    ],

    'captcha' => [
        'recaptcha_v3' => [
            'site_key' => 'your_recaptcha_v3_site_key',
            'secret_key' => 'your_recaptcha_v3_secret_key',
            'min_score' => 0.5,
            // Leave false when your key is registered only for the live domain.
            'allow_localhost' => false,
        ],
    ],
];
