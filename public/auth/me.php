<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(405, [
        'ok' => false,
        'message' => 'Method tidak diizinkan.'
    ]);
}

$user = requireLogin();

jsonResponse(200, [
    'ok' => true,
    'user' => $user,
    'csrf_token' => $_SESSION['csrf_token']
]);
