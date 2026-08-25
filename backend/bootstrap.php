<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-store');

function jsonResponse(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function input(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') return $_POST;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $_POST;
}

function requireMethod(string $method): void {
    if ($_SERVER['REQUEST_METHOD'] !== strtoupper($method)) jsonResponse(['success'=>false,'message'=>'Method not allowed'],405);
}

function currentUser(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    $stmt = Database::connection()->prepare('SELECT id, name, email, role, status FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$_SESSION['user_id']]);
    $user = $stmt->fetch();
    return ($user && $user['status'] === 'active') ? $user : null;
}

function requireAuth(): array {
    $user = currentUser();
    if (!$user) jsonResponse(['success'=>false,'message'=>'Authentication required'],401);
    return $user;
}

function requireRole(array $roles): array {
    $user = requireAuth();
    if (!in_array($user['role'], $roles, true)) jsonResponse(['success'=>false,'message'=>'Insufficient permissions'],403);
    return $user;
}

function csrfToken(): string {
    if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_expires']) || $_SESSION['csrf_expires'] < time()) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_expires'] = time() + CSRF_TTL;
    }
    return $_SESSION['csrf_token'];
}

function requireCsrf(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? (input()['csrf_token'] ?? '');
    if (!$token || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$token) || ($_SESSION['csrf_expires'] ?? 0) < time()) {
        jsonResponse(['success'=>false,'message'=>'Invalid or expired CSRF token'],419);
    }
}

function validateRequired(array $data, array $fields): void {
    $missing=[];
    foreach ($fields as $field) if (!isset($data[$field]) || trim((string)$data[$field]) === '') $missing[]=$field;
    if ($missing) jsonResponse(['success'=>false,'message'=>'Required fields are missing','fields'=>$missing],422);
}

function clean(string $value): string { return trim($value); }
function pagination(): array {
    $page=max(1,(int)($_GET['page']??1)); $perPage=min(100,max(1,(int)($_GET['per_page']??20)));
    return [$page,$perPage,($page-1)*$perPage];
}
function audit(int $userId, string $action, string $entity, int $entityId, ?array $metadata=null): void {
    $stmt=Database::connection()->prepare('INSERT INTO audit_logs (user_id,action,entity,entity_id,metadata,ip_address,created_at) VALUES (?,?,?,?,?,?,NOW())');
    $stmt->execute([$userId,$action,$entity,$entityId,$metadata?json_encode($metadata):null,$_SERVER['REMOTE_ADDR']??null]);
}
