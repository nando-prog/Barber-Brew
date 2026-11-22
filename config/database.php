<?php

declare(strict_types=1);

$host = '127.0.0.1';
// Port dicoba berurutan: 3307 (bawaan proyek ini), lalu 3306 (bawaan Laragon/XAMPP/MySQL).
$ports = ['3307', '3306'];
$db   = 'barber_brew';
$user = 'root';
$pass = '';

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_STRINGIFY_FETCHES  => false,
    PDO::ATTR_TIMEOUT            => 3,
];

$pdo = null;
$lastError = '';

foreach ($ports as $port) {
    try {
        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, $options);
        break;
    } catch (PDOException $e) {
        $lastError = "port {$port}: " . $e->getMessage();
        error_log('[DATABASE ERROR] ' . $lastError);
    }
}

if (!$pdo instanceof PDO) {
    // Selalu balas JSON agar frontend dapat menampilkan pesan yang jelas.
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'message' => 'Koneksi database gagal. Pastikan MySQL di Laragon menyala, '
            . 'database "' . $db . '" sudah diimpor, dan port/user/password di config/database.php sesuai.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
