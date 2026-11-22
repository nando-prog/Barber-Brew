<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(405, ['ok' => false, 'message' => 'Method tidak diizinkan.']);
}
$stmt = $pdo->query(
    "SELECT bp.id, u.name, bp.bio, bp.experience_years
     FROM barber_profiles bp
     INNER JOIN users u ON u.id = bp.user_id
     WHERE bp.is_available = 1 AND u.is_active = 1
     ORDER BY u.name"
);
jsonResponse(200, ['ok' => true, 'barbers' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
