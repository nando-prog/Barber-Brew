<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, [
        'ok' => false,
        'message' => 'Method tidak diizinkan.'
    ]);
}

verifyCsrf();

$data = requestData();

$email = strtolower(trim((string)($data['email'] ?? '')));
$password = (string)($data['password'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    jsonResponse(422, [
        'ok' => false,
        'message' => 'Email atau password tidak valid.'
    ]);
}

try {
    $stmt = $pdo->prepare(
        'SELECT id, name, email, password_hash, role, is_active
         FROM users
         WHERE email = ?
         LIMIT 1'
    );

    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (
        !$user ||
        !(int)$user['is_active'] ||
        !password_verify($password, $user['password_hash'])
    ) {
        jsonResponse(401, [
            'ok' => false,
            'message' => 'Email atau password tidak sesuai.'
        ]);
    }

    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id' => (int)$user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role']
    ];

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    jsonResponse(200, [
        'ok' => true,
        'message' => 'Login berhasil.',
        'user' => $_SESSION['user'],
        'csrf_token' => $_SESSION['csrf_token']
    ]);
} catch (PDOException $e) {
    error_log($e->getMessage());

    jsonResponse(500, [
        'ok' => false,
        'message' => 'Terjadi kesalahan pada server.'
    ]);
}
