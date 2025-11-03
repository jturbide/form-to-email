<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

// Detect and load test environment
$envFile = file_exists(dirname(__DIR__) . '/.env.test')
    ? dirname(__DIR__) . '/.env.test'
    : dirname(__DIR__) . '/.env';

if (is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with($line, '#')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $_ENV[$key] = $value;
    }
}

ini_set('display_errors', '1');
error_reporting(E_ALL);
