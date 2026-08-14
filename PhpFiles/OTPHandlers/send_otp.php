<?php
declare(strict_types=1);

// Retired: accepting an OTP value from the caller allowed arbitrary SMS sends.
// Active flows must use generate_otp.php, which generates, stores, throttles,
// and sends the OTP entirely on the server.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
http_response_code(410);
echo json_encode([
    'success' => false,
    'error' => 'This legacy OTP endpoint is disabled.',
], JSON_UNESCAPED_SLASHES);
exit;
