<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '', '/');
$base = trim(dirname($_SERVER['SCRIPT_NAME']), '/');
if ($base !== '' && str_starts_with($path, $base)) $path = trim(substr($path, strlen($base)), '/');
$method = $_SERVER['REQUEST_METHOD'];

if ($path === '' || $path === 'index.php') jsonResponse(['success'=>true,'application'=>APP_NAME,'version'=>'1.0.0','csrf_token'=>csrfToken()]);
require_once __DIR__ . '/routes_phase5.php';
require_once __DIR__ . '/routes_phase2.php';
require_once __DIR__ . '/routes_phase4.php';

$routes = [
 'GET api/csrf' => fn() => jsonResponse(['success'=>true,'csrf_token'=>csrfToken()]),
 'POST api/auth/login' => function(){ requireMethod('POST'); $d=input(); validateRequired($d,['email','password']); $s=Database::connection()->prepare('SELECT * FROM users WHERE email=? LIMIT 1'); $s->execute([strtolower(clean($d['email']))]); $u=$s->fetch(); if(!$u || !password_verify((string)$d['password'],$u['password_hash']) || $u['status']!=='active') jsonResponse(['success'=>false,'message'=>'Invalid credentials'],401); session_regenerate_id(true); $_SESSION['user_id']=(int)$u['id']; csrfToken(); Database::connection()->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?')->execute([$u['id']]); audit((int)$u['id'],'login','user',(int)$u['id']); unset($u['password_hash']); jsonResponse(['success'=>true,'user'=>$u,'csrf_token'=>csrfToken()]); },
 'POST api/auth/logout' => function(){ $u=requireAuth(); requireCsrf(); audit((int)$u['id'],'logout','user',(int)$u['id']); $_SESSION=[]; session_destroy(); jsonResponse(['success'=>true,'message'=>'Logged out']); },
 'GET api/auth/me' => function(){ $u=requireAuth(); jsonResponse(['success'=>true,'user'=>$u,'csrf_token'=>csrfToken()]); },
];
$key="$method $path"; if(isset($routes[$key])){$routes[$key]();exit;}

require_once __DIR__ . '/routes_core.php';
jsonResponse(['success'=>false,'message'=>'Endpoint not found'],404);
