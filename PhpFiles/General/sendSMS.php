<?php
// ===== CONFIG =====
$SEMAPHORE_API_KEY = 'ee267d0fbd5c2159bea7d72878c9d4cb';
$SEMAPHORE_SENDER  = 'SEMAPHORE';

/**
 * Send SMS (OTP)
 * @param string $recipient Phone number (09xxxxxxxxx)
 * @param string $message   Message text
 * @param string|null $otpCode OTP code
 * @return bool true if sent, false if failed
 */
function sendSMS(string $recipient, string $message, string $otpCode = null): bool {
    global $SEMAPHORE_API_KEY, $SEMAPHORE_SENDER;

    if (empty($SEMAPHORE_API_KEY) || empty($SEMAPHORE_SENDER)) {
        error_log('Semaphore API Key or Sender Name is missing');
        return false;
    }

    // Ensure PH format (09xxxxxxxxx)
    $recipient = preg_replace('/[^0-9]/', '', $recipient);

    $parameters = [
        'apikey'     => $SEMAPHORE_API_KEY,
        'number'     => $recipient,
        'message'    => $message,
        'sendername' => $SEMAPHORE_SENDER
    ];

    // Use OTP endpoint if OTP is provided
    if ($otpCode) {
        $parameters['code'] = $otpCode;
        $url = 'https://semaphore.co/api/v4/messages';
    } else {
        $url = 'https://semaphore.co/api/v4/messages';
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($parameters),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30
    ]);

    $output = curl_exec($ch);

    if ($output === false) {
        error_log('cURL Error: ' . curl_error($ch));
        curl_close($ch);
        return false;
    }

    curl_close($ch);

    $response = json_decode($output, true);

    // Semaphore success response contains message_id or code
    if (isset($response[0]['message_id']) || isset($response['code'])) {
        return true;
    }

    error_log('Semaphore Error: ' . $output);
    return false;
}
?>