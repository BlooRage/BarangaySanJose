<?php
declare(strict_types=1);

require_once __DIR__ . '/runtimeConfig.php';

// SMS credentials prefer environment/runtime config, with app fallbacks kept here.
$defaultSemaphoreApiKey = 'ee267d0fbd5c2159bea7d72878c9d4cb';
$defaultSemaphoreSender = 'BrgySanJose';

if (!function_exists('smsRuntimeConfig')) {
    function smsRuntimeConfig(): array
    {
        $defaultApiKey = 'ee267d0fbd5c2159bea7d72878c9d4cb';
        $defaultSender = 'BrgySanJose';

        return [
            'api_key' => trim((string)runtime_env(
                'SMS_SEMAPHORE_API_KEY',
                runtime_env('SMS_API_KEY', runtime_config('sms.semaphore_api_key', $defaultApiKey))
            )),
            'sender' => trim((string)runtime_env('SMS_SENDER', runtime_config('sms.sender', $defaultSender))),
            'endpoint' => trim((string)runtime_env('SMS_ENDPOINT', runtime_config('sms.endpoint', 'https://api.semaphore.co/api/v4/messages'))),
            'otp_endpoint' => trim((string)runtime_env('SMS_OTP_ENDPOINT', runtime_config('sms.otp_endpoint', 'https://api.semaphore.co/api/v4/otp'))),
        ];
    }
}

if (!array_key_exists('LAST_SMS_ERROR', $GLOBALS)) {
    $GLOBALS['LAST_SMS_ERROR'] = '';
}

if (!function_exists('setLastSmsError')) {
    function setLastSmsError(string $message): void
    {
        $GLOBALS['LAST_SMS_ERROR'] = trim($message);
    }
}

if (!function_exists('getLastSmsError')) {
    function getLastSmsError(): string
    {
        return trim((string)($GLOBALS['LAST_SMS_ERROR'] ?? ''));
    }
}

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

if (!function_exists('smsMessageWithOtpPlaceholder')) {
    function smsMessageWithOtpPlaceholder(string $message, string $otpCode): string
    {
        if ($otpCode === '') {
            return $message;
        }

        if (strpos($message, '{otp}') !== false) {
            return $message;
        }

        $count = 0;
        $updated = str_replace($otpCode, '{otp}', $message, $count);
        return $count > 0 ? $updated : $message;
    }
}

if (!function_exists('smsHydrateOtpMessage')) {
    function smsHydrateOtpMessage(string $message, string $otpCode): string
    {
        if ($otpCode === '') {
            return $message;
        }

        return str_replace('{otp}', $otpCode, $message);
    }
}

if (!function_exists('smsFormatFailure')) {
    function smsFormatFailure(int $httpCode, string $body, string $transportError = ''): string
    {
        $parts = [];
        if ($transportError !== '') {
            $parts[] = $transportError;
        }
        if ($httpCode > 0) {
            $parts[] = 'HTTP ' . $httpCode;
        }

        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            $rows = array_is_list($decoded) ? $decoded : [$decoded];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                foreach (['message', 'error', 'status', 'details'] as $key) {
                    $value = trim((string)($row[$key] ?? ''));
                    if ($value !== '') {
                        $parts[] = $value;
                    }
                }
            }
        } elseif (trim($body) !== '') {
            $parts[] = trim($body);
        }

        $parts = array_values(array_unique(array_filter($parts, static fn($part) => $part !== '')));
        return $parts !== [] ? implode(' | ', $parts) : 'Unknown SMS gateway failure.';
    }
}

if (!function_exists('smsHttpPostForm')) {
    function smsHttpPostForm(string $endpoint, array $parameters): array
    {
        $payload = http_build_query($parameters);

        if (function_exists('curl_init')) {
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'Content-Type: application/x-www-form-urlencoded',
                ],
                CURLOPT_TIMEOUT => 30,
            ]);

            $output = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $transportError = '';
            if ($output === false) {
                $transportError = 'cURL Error: ' . curl_error($ch);
            }
            curl_close($ch);

            return [
                'ok' => $output !== false,
                'http_code' => $httpCode,
                'body' => $output !== false ? (string)$output : '',
                'transport_error' => $transportError,
            ];
        }

        if (!runtime_bool(ini_get('allow_url_fopen'), false)) {
            return [
                'ok' => false,
                'http_code' => 0,
                'body' => '',
                'transport_error' => 'cURL is unavailable and allow_url_fopen is disabled.',
            ];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'Accept: application/json',
                    'Content-Type: application/x-www-form-urlencoded',
                ]),
                'content' => $payload,
                'timeout' => 30,
                'ignore_errors' => true,
            ],
        ]);

        $output = @file_get_contents($endpoint, false, $context);
        $httpCode = 0;
        foreach ((array)($http_response_header ?? []) as $headerLine) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~i', (string)$headerLine, $matches)) {
                $httpCode = (int)$matches[1];
                break;
            }
        }

        return [
            'ok' => ($output !== false) || $httpCode > 0,
            'http_code' => $httpCode,
            'body' => $output !== false ? (string)$output : '',
            'transport_error' => $output === false ? 'HTTP stream request failed.' : '',
        ];
    }
}

if (!function_exists('smsSendRequest')) {
    function smsSendRequest(string $endpoint, array $parameters): array
    {
        $http = smsHttpPostForm($endpoint, $parameters);
        $body = (string)($http['body'] ?? '');
        $httpCode = (int)($http['http_code'] ?? 0);
        $decoded = json_decode($body, true);

        return [
            'success' => !empty($http['ok']) && $httpCode >= 200 && $httpCode < 300 && smsResponseIndicatesSuccess($decoded),
            'http_code' => $httpCode,
            'body' => $body,
            'response' => $decoded,
            'transport_error' => trim((string)($http['transport_error'] ?? '')),
        ];
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
    $config = smsRuntimeConfig();
    $apiKey = (string)($config['api_key'] ?? '');
    $sender = (string)($config['sender'] ?? '');
    $messagesEndpoint = (string)($config['endpoint'] ?? '');
    $otpEndpoint = (string)($config['otp_endpoint'] ?? '');

    setLastSmsError('');

    if ($apiKey === '' || $sender === '') {
        $error = 'SMS sending unavailable: Semaphore API key or sender is missing.';
        setLastSmsError($error);
        error_log('[sendSMS] ' . $error);
        return false;
    }

    $recipient = normalizeSmsRecipient($recipient);
    if ($recipient === '') {
        $error = 'SMS sending unavailable: Invalid recipient number supplied.';
        setLastSmsError($error);
        error_log('[sendSMS] ' . $error);
        return false;
    }

    $message = trim($message);
    if ($message === '') {
        $error = 'SMS sending unavailable: Message body is empty.';
        setLastSmsError($error);
        error_log('[sendSMS] ' . $error);
        return false;
    }

    $parameters = [
        'apikey' => $apiKey,
        'number' => $recipient,
        'message' => $message,
        'sendername' => $sender,
    ];

    $endpoint = $messagesEndpoint !== '' ? $messagesEndpoint : 'https://api.semaphore.co/api/v4/messages';
    if ($otpCode !== null && $otpCode !== '') {
        $otpParameters = $parameters;
        $otpParameters['message'] = smsMessageWithOtpPlaceholder($message, $otpCode);
        $otpParameters['code'] = $otpCode;
        $otpEndpoint = $otpEndpoint !== '' ? $otpEndpoint : 'https://api.semaphore.co/api/v4/otp';

        $otpAttempt = smsSendRequest($otpEndpoint, $otpParameters);
        if ($otpAttempt['success']) {
            return true;
        }

        $otpFailure = smsFormatFailure(
            (int)$otpAttempt['http_code'],
            (string)$otpAttempt['body'],
            (string)$otpAttempt['transport_error']
        );
        error_log('[sendSMS] OTP endpoint failed, retrying messages endpoint: ' . $otpFailure);

        $parameters['message'] = smsHydrateOtpMessage($otpParameters['message'], $otpCode);
    }

    $attempt = smsSendRequest($endpoint, $parameters);
    if ($attempt['success']) {
        return true;
    }

    $error = smsFormatFailure(
        (int)$attempt['http_code'],
        (string)$attempt['body'],
        (string)$attempt['transport_error']
    );
    setLastSmsError($error);
    error_log('[sendSMS] ' . $error);
    return false;
}
