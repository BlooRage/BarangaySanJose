<?php
declare(strict_types=1);

require_once __DIR__ . '/runtimeConfig.php';

$SEMAPHORE_API_KEY = trim((string)runtime_env('SMS_SEMAPHORE_API_KEY', runtime_env('SMS_API_KEY', runtime_config('sms.semaphore_api_key', ''))));
$SEMAPHORE_SENDER = trim((string)runtime_env('SMS_SENDER', runtime_config('sms.sender', 'BrgySanJose')));
$SEMAPHORE_ENDPOINT = trim((string)runtime_env('SMS_ENDPOINT', runtime_config('sms.endpoint', 'https://api.semaphore.co/api/v4/messages')));

if (!function_exists('normalizeSmsRecipient')) {
    function normalizeSmsRecipient(string $recipient): string
    {
        $digits = preg_replace('/\D+/', '', $recipient);
        if ($digits === '') {
            return '';
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            return '0' . $digits;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return $digits;
        }

        if (strlen($digits) === 12 && str_starts_with($digits, '63')) {
            return '0' . substr($digits, 2);
        }

        return '';
    }
}

if (!function_exists('smsResponseIndicatesSuccess')) {
    function smsResponseIndicatesSuccess($response): bool
    {
        if (!is_array($response)) {
            return false;
        }

        $rows = array_is_list($response) ? $response : [$response];
        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['message_id'])) {
                continue;
            }

            $status = strtolower(trim((string)($row['status'] ?? '')));
            if (in_array($status, ['failed', 'refunded', 'rejected', 'invalid'], true)) {
                continue;
            }

            return true;
        }

        return false;
    }
}

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

    $recipient = normalizeSmsRecipient($recipient);
    if ($recipient === '') {
        error_log('SMS sending unavailable: Invalid recipient number supplied.');
        return false;
    }

    $message = trim($message);
    if ($message === '') {
        error_log('SMS sending unavailable: Message body is empty.');
        return false;
    }

    $parameters = [
        'apikey' => $SEMAPHORE_API_KEY,
        'number' => $recipient,
        'message' => $message,
        'sendername' => $SEMAPHORE_SENDER,
    ];

    if ($otpCode !== null && $otpCode !== '') {
        $parameters['code'] = $otpCode;
    }

    $endpoint = $SEMAPHORE_ENDPOINT !== '' ? $SEMAPHORE_ENDPOINT : 'https://api.semaphore.co/api/v4/messages';
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($parameters),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_TIMEOUT => 30,
    ]);

    $output = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

    if ($output === false) {
        error_log('cURL Error: ' . curl_error($ch));
        curl_close($ch);
        return false;
    }

    curl_close($ch);

    $response = json_decode($output, true);
    if ($httpCode >= 200 && $httpCode < 300 && smsResponseIndicatesSuccess($response)) {
        return true;
    }

    error_log('Semaphore Error (HTTP ' . $httpCode . '): ' . $output);
    return false;
}
