<?php
declare(strict_types=1);

/*
 * Configuration is read from the .env file in the project root (outside the
 * public web folder, so it can never be downloaded). Copy .env.example to .env.
 */
(function (string $file): void {
    if (!is_file($file)) {
        return;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        $_ENV[$k] = trim($v, "\"'");
    }
})(ROOT . '/.env');

function cfg(string $key, ?string $default = null): ?string
{
    if (isset($_ENV[$key])) {
        return $_ENV[$key];
    }
    $v = getenv($key);
    return $v !== false ? $v : $default;
}

define('APP_NAME', 'Hello Brew!');
define('APP_ENV', cfg('APP_ENV', 'production'));
define('APP_BASE', rtrim((string) cfg('APP_BASE', ''), '/'));   // e.g. /hello-brew/public on XAMPP
define('APP_SECRET', (string) cfg('APP_SECRET', ''));
define('CURRENCY', '₱');
define('DELIVERY_FEE', 50.00);
define('FREE_DELIVERY_MIN', 800.00);
define('LOW_STOCK_THRESHOLD', 10);
define('RETURN_WINDOW_DAYS', 7);
define('SESSION_IDLE_SECONDS', 1800);
define('MAX_UPLOAD_BYTES', 2 * 1024 * 1024);

define('CAFE_EMAIL', cfg('CAFE_EMAIL', 'hello@hellobrew.local'));
define('CAFE_PHONE', cfg('CAFE_PHONE', '+63 900 000 0000'));
define('CAFE_ADDRESS', cfg('CAFE_ADDRESS', 'Your street, Your city, Philippines'));

if (APP_SECRET === '' || strlen(APP_SECRET) < 32) {
    http_response_code(500);
    exit('Configuration error: set APP_SECRET (32+ random characters) in .env. Run: php database/setup.php');
}

ini_set('display_errors', APP_ENV === 'development' ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set(cfg('APP_TIMEZONE', 'Asia/Manila'));
