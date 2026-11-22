<?php
require_once __DIR__ . '/../../app/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(405, ['ok'=>false,'message'=>'Method tidak diizinkan.']);
requireLogin();
verifyCsrf();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
jsonResponse(200, ['ok'=>true,'message'=>'Anda telah logout.']);
