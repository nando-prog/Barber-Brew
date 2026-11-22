<?php
declare(strict_types=1);

function jsonResponse(int $status, array $data): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function requireLogin(): array {
    if (empty($_SESSION['user'])) {
        jsonResponse(401, ['ok' => false, 'message' => 'Silakan masuk terlebih dahulu.']);
    }
    return $_SESSION['user'];
}

function requireRole(array $roles): array {
    $user = requireLogin();
    if (!in_array($user['role'], $roles, true)) {
        jsonResponse(403, ['ok' => false, 'message' => 'Anda tidak memiliki akses.']);
    }
    return $user;
}


function verifyCsrf(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'] ?? '', $token)
    ) {
        jsonResponse(403, [
            'ok' => false,
            'message' => 'Token keamanan tidak valid. Muat ulang halaman.'
        ]);
    }
}


function requestData(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);
    return is_array($data) ? $data : $_POST;
}
