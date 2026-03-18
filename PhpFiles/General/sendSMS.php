<?php
declare(strict_types=1);

require_once __DIR__ . '/runtimeConfig.php';

$SEMAPHORE_API_KEY = trim((string)runtime_env('SMS_SEMAPHORE_API_KEY', runtime_env('SMS_API_KEY', runtime_config('sms.semaphore_api_key', ''))));
$SEMAPHORE_SENDER = trim((string)runtime_env('SMS_SENDER', runtime_config('sms.sender', 'BrgySanJose')));
$SEMAPHORE_ENDPOINT = trim((string)runtime_env('SMS_ENDPOINT', runtime_config('sms.endpoint', 'https://semaphore.co/api/v4/messages')));

/**
 * Send SMS (OTP)
 * @param string $recipient Phone number (09xxxxxxxxx)
 * @param string $message   Message text
 * @param string|null $otpCode OTP code
 * @return bool true if sent, false if failed
 */
function sendSMS(string $recipient, string $message, string $otpCode = null): bool
{
    global $SEMAPHORE_API_KEY, $SEMAPHORE_SENDER, $SEMAPHORE_ENDPOINT;

    if (!function_exists('curl_init')) {
        error_log('SMS sending unavailable: cURL extension is not enabled.');
        return false;
    }

    if ($SEMAPHORE_API_KEY === '' || $SEMAPHORE_SENDER === '') {
        error_log('SMS sending unavailable: Semaphore API key or sender is missing.');
        return false;
    }

    $recipient = preg_replace('/[^0-9]/', '', $recipient);

    $parameters = [
        'apikey' => $SEMAPHORE_API_KEY,
        'number' => $recipient,
        'message' => $message,
        'sendername' => $SEMAPHORE_SENDER,
    ];

    if ($otpCode !== null && $otpCode !== '') {
        $parameters['code'] = $otpCode;
    }

    $endpoint = $SEMAPHORE_ENDPOINT !== '' ? $SEMAPHORE_ENDPOINT : 'https://semaphore.co/api/v4/messages';
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($parameters),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);

    $output = curl_exec($ch);

    if ($output === false) {
        error_log('cURL Error: ' . curl_error($ch));
        curl_close($ch);
        return false;
    }

    curl_close($ch);

    $response = json_decode($output, true);
    if (isset($response[0]['message_id']) || isset($response['code'])) {
        return true;
    }

    error_log('Semaphore Error: ' . $output);
    return false;
}
