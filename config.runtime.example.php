<?php
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
        'from_email' => 'official@your-domain.example',
        'from_name' => 'Barangay San Jose',
        'senders' => [
            'verify' => [
                'from_email' => 'verify@your-domain.example',
                'from_name' => 'Barangay San Jose Verification',
            ],
            'one_time' => [
                'from_email' => 'access@your-domain.example',
                'from_name' => 'Barangay San Jose Access',
            ],
            'onboarding_access' => [
                'from_email' => 'access@your-domain.example',
                'from_name' => 'Barangay San Jose',
            ],
            'announcement' => [
                'from_email' => 'announcements@your-domain.example',
                'from_name' => 'Barangay San Jose Announcements',
            ],
            'transaction' => [
                'from_email' => 'no-reply@your-domain.example',
                'from_name' => 'Barangay San Jose Notifications',
            ],
        ],
    ],

    'sms' => [
        'semaphore_api_key' => 'your_semaphore_api_key',
        'sender' => 'BrgySanJose',
        'endpoint' => 'https://semaphore.co/api/v4/messages',
    ],
];
