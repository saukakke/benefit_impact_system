<?php
declare(strict_types=1);

const APP_NAME = 'UCSI Beneficiary Management and Impact Tracking System';
const APP_ENV = 'production';
const APP_DEBUG = false;
const APP_TIMEZONE = 'Africa/Lagos';
const DB_HOST = '127.0.0.1';
const DB_PORT = '3306';
const DB_NAME = 'ucsi_benefit_impact';
const DB_USER = '';
const DB_PASS = '';
const SESSION_NAME = 'ucsi_session';
const SESSION_LIFETIME = 7200;
const CSRF_TTL = 7200;
const PASSWORD_MIN_LENGTH = 8;
const UPLOAD_MAX_BYTES = 5242880;
const UPLOAD_DIR = __DIR__ . '/../storage/uploads';

date_default_timezone_set(APP_TIMEZONE);

function envValue(string $key, ?string $default = null): ?string {
    $value = getenv($key);
    return $value === false ? $default : $value;
}

function requireEnvironment(array $keys): void {
    $missing = [];
    foreach ($keys as $key) {
        if (envValue($key) === null || envValue($key) === '') $missing[] = $key;
    }
    if ($missing) {
        error_log('Missing required environment variables: ' . implode(', ', $missing));
        http_response_code(500);
        exit('Application configuration error.');
    }
}

if (APP_ENV === 'production') {
    requireEnvironment(['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS']);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

if (APP_ENV === 'production' && (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
