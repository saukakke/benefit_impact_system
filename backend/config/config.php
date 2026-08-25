<?php
declare(strict_types=1);

const APP_NAME = 'UCSI Beneficiary Management and Impact Tracking System';
const APP_ENV = 'production';
const APP_DEBUG = false;
const APP_TIMEZONE = 'Africa/Lagos';
const DB_HOST = '127.0.0.1';
const DB_PORT = '3306';
const DB_NAME = 'ucsi_benefit_impact';
const DB_USER = 'root';
const DB_PASS = '';
const SESSION_NAME = 'ucsi_session';
const SESSION_LIFETIME = 7200;
const CSRF_TTL = 7200;
const PASSWORD_MIN_LENGTH = 8;
const UPLOAD_MAX_BYTES = 5242880;
const UPLOAD_DIR = __DIR__ . '/../storage/uploads';

date_default_timezone_set(APP_TIMEZONE);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}
