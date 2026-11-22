<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(405, [
        'ok' => false,
        'message' => 'Method tidak diizinkan.'
    ]);
}

$user = requireRole(['barber']);

$stmt = $pdo->prepare(
    "SELECT
        b.id,
        b.booking_date,
        b.start_time,
        b.end_time,
        b.status AS booking_status,
        b.customer_note,
        u.name AS customer_name,
        u.phone,
        s.name AS service_name,
        b.service_price_snapshot,
        o.order_code,
        o.status AS order_status,
        o.payment_status
    FROM barber_profiles bp
    INNER JOIN bookings b ON b.barber_id = bp.id
    INNER JOIN users u ON u.id = b.customer_id
    INNER JOIN services s ON s.id = b.service_id
    LEFT JOIN orders o ON o.booking_id = b.id
    WHERE bp.user_id = ?
    ORDER BY b.booking_date ASC, b.start_time ASC"
);

$stmt->execute([$user['id']]);

$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

jsonResponse(200, [
    'ok' => true,
    'bookings' => $bookings,
    'csrf_token' => $_SESSION['csrf_token']
]);
