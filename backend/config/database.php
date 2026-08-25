<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

final class Database {
    private static ?PDO $connection = null;

    public static function connection(): PDO {
        if (self::$connection instanceof PDO) return self::$connection;

        $host = envValue('DB_HOST', DB_HOST);
        $port = envValue('DB_PORT', DB_PORT);
        $name = envValue('DB_NAME', DB_NAME);
        $user = envValue('DB_USER');
        $pass = envValue('DB_PASS');

        if ($user === null || $pass === null) {
            throw new RuntimeException('Database credentials are not configured.');
        }

        $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $name . ';charset=utf8mb4';
        self::$connection = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
        return self::$connection;
    }

    private function __construct() {}
}
