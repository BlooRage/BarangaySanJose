<?php
declare(strict_types=1);

require_once __DIR__ . '/runtimeConfig.php';

if (!function_exists('recaptcha_request_is_localhost')) {
    function recaptcha_request_is_localhost(): bool
    {
        $hostCandidates = [
            (string)($_SERVER['HTTP_HOST'] ?? ''),
            (string)($_SERVER['SERVER_NAME'] ?? ''),
        ];

        foreach ($hostCandidates as $candidate) {
            $candidate = strtolower(trim((string)explode(',', $candidate)[0]));
            if ($candidate === '') {
                continue;
            }

            $hostOnly = preg_replace('/:\d+$/', '', $candidate);
            if (in_array($hostOnly, ['localhost', '127.0.0.1', '::1'], true)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('recaptcha_v3_site_key')) {
    function recaptcha_v3_site_key(): string
    {
        return trim((string)runtime_env(
            'RECAPTCHA_V3_SITE_KEY',
            runtime_config('captcha.recaptcha_v3.site_key', '')
        ));
    }
}

if (!function_exists('recaptcha_v3_secret_key')) {
    function recaptcha_v3_secret_key(): string
    {
        return trim((string)runtime_env(
            'RECAPTCHA_V3_SECRET_KEY',
            runtime_config('captcha.recaptcha_v3.secret_key', '')
        ));
    }
}

if (!function_exists('recaptcha_v3_min_score')) {
    function recaptcha_v3_min_score(): float
    {
        $configured = runtime_env(
            'RECAPTCHA_V3_MIN_SCORE',
            runtime_config('captcha.recaptcha_v3.min_score', 0.5)
        );

        $score = is_numeric($configured) ? (float)$configured : 0.5;
        if ($score < 0.0) {
            return 0.0;
        }
        if ($score > 1.0) {
            return 1.0;
        }

        return $score;
    }
}

if (!function_exists('recaptcha_v3_allow_localhost')) {
    function recaptcha_v3_allow_localhost(): bool
    {
        return runtime_bool(
            runtime_env(
                'RECAPTCHA_V3_ALLOW_LOCALHOST',
                runtime_config('captcha.recaptcha_v3.allow_localhost', false)
            ),
            false
        );
    }
}

if (!function_exists('recaptcha_v3_is_configured')) {
    function recaptcha_v3_is_configured(): bool
    {
        return recaptcha_v3_site_key() !== '' && recaptcha_v3_secret_key() !== '';
    }
}

if (!function_exists('recaptcha_v3_frontend_enabled')) {
    function recaptcha_v3_frontend_enabled(): bool
    {
        if (!recaptcha_v3_is_configured()) {
            return false;
        }

        if (recaptcha_request_is_localhost() && !recaptcha_v3_allow_localhost()) {
            return false;
        }

        return true;
    }
}

if (!function_exists('recaptcha_v3_should_enforce')) {
    function recaptcha_v3_should_enforce(): bool
    {
        return recaptcha_v3_frontend_enabled();
    }
}

if (!function_exists('recaptcha_client_ip')) {
    function recaptcha_client_ip(): string
    {
        $keys = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'REMOTE_ADDR',
        ];

        foreach ($keys as $key) {
            $raw = trim((string)($_SERVER[$key] ?? ''));
            if ($raw === '') {
                continue;
            }

            $ip = trim((string)explode(',', $raw)[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return '';
    }
}

if (!function_exists('recaptcha_v3_post_verify')) {
    function recaptcha_v3_post_verify(array $payload): array
    {
        $endpoint = 'https://www.google.com/recaptcha/api/siteverify';
        $postBody = http_build_query($payload);

        if (function_exists('curl_init')) {
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postBody,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded',
                ],
            ]);
            $responseBody = curl_exec($ch);
            $curlError = curl_error($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if (!is_string($responseBody) || $responseBody === '') {
                return [
                    'success' => false,
                    'message' => $curlError !== ''
                        ? 'Security verification could not be completed. Please try again.'
                        : 'Security verification returned an empty response. Please try again.',
                    'http_code' => $httpCode,
                    'raw' => null,
                ];
            }
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                    'content' => $postBody,
                    'timeout' => 8,
                ],
            ]);
            $responseBody = @file_get_contents($endpoint, false, $context);
            if (!is_string($responseBody) || $responseBody === '') {
                return [
                    'success' => false,
                    'message' => 'Security verification could not be completed. Please try again.',
                    'http_code' => 0,
                    'raw' => null,
                ];
            }
        }

        $decoded = json_decode($responseBody, true);
        if (!is_array($decoded)) {
            return [
                'success' => false,
                'message' => 'Security verification returned an invalid response. Please try again.',
                'raw' => null,
            ];
        }

        return [
            'success' => true,
            'raw' => $decoded,
        ];
    }
}

if (!function_exists('recaptcha_v3_verify')) {
    function recaptcha_v3_verify(string $token, string $expectedAction, ?float $minimumScore = null): array
    {
        if (!recaptcha_v3_should_enforce()) {
            return [
                'success' => true,
                'skipped' => true,
                'score' => null,
                'action' => '',
                'message' => '',
            ];
        }

        $token = trim($token);
        if ($token === '') {
            return [
                'success' => false,
                'message' => 'Please complete the security check and try again.',
            ];
        }

        $payload = [
            'secret' => recaptcha_v3_secret_key(),
            'response' => $token,
        ];

        $remoteIp = recaptcha_client_ip();
        if ($remoteIp !== '') {
            $payload['remoteip'] = $remoteIp;
        }

        $result = recaptcha_v3_post_verify($payload);
        if (empty($result['success']) || !is_array($result['raw'] ?? null)) {
            return [
                'success' => false,
                'message' => (string)($result['message'] ?? 'Security verification could not be completed. Please try again.'),
            ];
        }

        $raw = $result['raw'];
        $verified = !empty($raw['success']);
        $action = trim((string)($raw['action'] ?? ''));
        $score = isset($raw['score']) && is_numeric($raw['score']) ? (float)$raw['score'] : null;
        $threshold = $minimumScore ?? recaptcha_v3_min_score();

        if (!$verified) {
            return [
                'success' => false,
                'message' => 'Security verification failed. Please try again.',
                'action' => $action,
                'score' => $score,
                'error_codes' => $raw['error-codes'] ?? [],
            ];
        }

        if ($expectedAction !== '' && $action !== $expectedAction) {
            return [
                'success' => false,
                'message' => 'Security verification did not match the current request. Please try again.',
                'action' => $action,
                'score' => $score,
            ];
        }

        if ($score !== null && $score < $threshold) {
            return [
                'success' => false,
                'message' => 'Security verification flagged this request as suspicious. Please try again.',
                'action' => $action,
                'score' => $score,
            ];
        }

        return [
            'success' => true,
            'action' => $action,
            'score' => $score,
            'threshold' => $threshold,
            'raw' => $raw,
        ];
    }
}
