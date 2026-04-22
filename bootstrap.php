<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Makassar');

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $filePath = __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

    if (is_file($filePath)) {
        require_once $filePath;
    }
});

/**
 * @return mixed
 */
function app_config(?string $key = null, mixed $default = null): mixed
{
    static $config = null;

    if ($config === null) {
        $config = require __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';
    }

    if ($key === null) {
        return $config;
    }

    $segments = explode('.', $key);
    $value = $config;

    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }

        $value = $value[$segment];
    }

    return $value;
}

function app_url(string $path = ''): string
{
    $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
    $normalizedDir = $scriptDir === DIRECTORY_SEPARATOR || $scriptDir === '.' ? '' : str_replace('\\', '/', rtrim($scriptDir, '/\\'));
    $normalizedPath = ltrim($path, '/');

    if ($normalizedPath === '') {
        return $scheme . '://' . $host . $normalizedDir . '/';
    }

    return $scheme . '://' . $host . $normalizedDir . '/' . $normalizedPath;
}

function client_ip(): string
{
    $forwardedFor = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($forwardedFor !== '') {
        $parts = explode(',', $forwardedFor);
        return trim($parts[0]);
    }

    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

function client_user_agent(): string
{
    return (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
}
