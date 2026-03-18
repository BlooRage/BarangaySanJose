<?php
declare(strict_types=1);

if (!function_exists('runtime_project_root')) {
    function runtime_project_root(): string
    {
        static $root = null;
        if ($root !== null) {
            return $root;
        }

        $resolved = realpath(__DIR__ . '/../../');
        $root = $resolved !== false ? $resolved : dirname(__DIR__, 2);
        return $root;
    }
}

if (!function_exists('runtime_env')) {
    function runtime_env(string $key, $default = null)
    {
        $candidates = [
            getenv($key),
            $_ENV[$key] ?? null,
            $_SERVER[$key] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === false || $candidate === null) {
                continue;
            }
            if (is_string($candidate) && trim($candidate) === '') {
                continue;
            }
            return $candidate;
        }

        return $default;
    }
}

if (!function_exists('runtime_bool')) {
    function runtime_bool($value, bool $default = false): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null) {
            return $default;
        }

        $normalized = strtolower(trim((string)$value));
        if ($normalized === '') {
            return $default;
        }

        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return $default;
    }
}

if (!function_exists('runtime_parse_database_url')) {
    function runtime_parse_database_url(string $databaseUrl): array
    {
        $databaseUrl = trim($databaseUrl);
        if ($databaseUrl === '') {
            return [];
        }

        $parts = parse_url($databaseUrl);
        if (!is_array($parts)) {
            return [];
        }

        $config = [];
        if (isset($parts['host'])) {
            $config['host'] = (string)$parts['host'];
        }
        if (isset($parts['port'])) {
            $config['port'] = (int)$parts['port'];
        }
        if (isset($parts['user'])) {
            $config['user'] = rawurldecode((string)$parts['user']);
        }
        if (isset($parts['pass'])) {
            $config['pass'] = rawurldecode((string)$parts['pass']);
        }
        if (!empty($parts['path'])) {
            $config['name'] = ltrim((string)$parts['path'], '/');
        }

        return $config;
    }
}

if (!function_exists('runtime_load_config_file')) {
    function runtime_load_config_file(string $path): array
    {
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            return [];
        }

        $loaded = require $path;
        return is_array($loaded) ? $loaded : [];
    }
}

if (!function_exists('runtime_config_sources')) {
    function runtime_config_sources(): array
    {
        $root = runtime_project_root();
        $paths = [
            $root . '/config.runtime.php',
            __DIR__ . '/runtime.php',
            $root . '/config.runtime.local.php',
            __DIR__ . '/runtime.local.php',
        ];

        $envPath = trim((string)runtime_env('APP_RUNTIME_CONFIG', ''));
        if ($envPath !== '') {
            if (!preg_match('/^(?:\/|[A-Za-z]:[\/\\\\])/', $envPath)) {
                $envPath = $root . '/' . ltrim($envPath, '/');
            }
            $paths[] = $envPath;
        }

        return array_values(array_unique($paths));
    }
}

if (!function_exists('runtime_config_all')) {
    function runtime_config_all(): array
    {
        static $config = null;
        if ($config !== null) {
            return $config;
        }

        $config = [];
        foreach (runtime_config_sources() as $path) {
            $config = array_replace_recursive($config, runtime_load_config_file($path));
        }

        $databaseUrl = trim((string)runtime_env('DATABASE_URL', (string)($config['db']['url'] ?? '')));
        if ($databaseUrl !== '') {
            $config['db'] = array_replace(
                (array)($config['db'] ?? []),
                runtime_parse_database_url($databaseUrl)
            );
        }

        return $config;
    }
}

if (!function_exists('runtime_config')) {
    function runtime_config(string $key, $default = null)
    {
        $config = runtime_config_all();
        if ($key === '') {
            return $config;
        }

        $value = $config;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}
