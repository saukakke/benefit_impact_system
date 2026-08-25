<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

try {
    $db = Database::connection();
    $db->query('SELECT 1');
    http_response_code(200);
    echo json_encode([
        'status' => 'ok',
        'application' => APP_NAME,
        'environment' => APP_ENV,
        'database' => 'ok',
        'timestamp' => gmdate('c')
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('Health check failed: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode([
        'status' => 'error',
        'application' => APP_NAME,
        'database' => 'unavailable'
    ], JSON_UNESCAPED_SLASHES);
}
