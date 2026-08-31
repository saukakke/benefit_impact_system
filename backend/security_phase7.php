<?php
declare(strict_types=1);

function phase7SecurityHeaders(): void {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cache-Control: no-store');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}
function phase7RateLimit(string $bucket, int $limit = 120, int $window = 60): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = 'phase7_rl_' . hash('sha256', $bucket.'|'.$ip);
    $now = time();
    $entry = $_SESSION[$key] ?? ['start'=>$now,'count'=>0];
    if (($now - $entry['start']) >= $window) $entry=['start'=>$now,'count'=>0];
    $entry['count']++;
    $_SESSION[$key]=$entry;
    if ($entry['count'] > $limit) {
        header('Retry-After: '.max(1,$window-($now-$entry['start'])));
        jsonResponse(['success'=>false,'message'=>'Too many requests'],429);
    }
}
function phase7PreventSessionFixation(): void {
    if (!empty($_SESSION['user_id']) && empty($_SESSION['phase7_session_regenerated'])) {
        session_regenerate_id(true);
        $_SESSION['phase7_session_regenerated']=true;
    }
}
phase7SecurityHeaders();
phase7RateLimit('api');
phase7PreventSessionFixation();
