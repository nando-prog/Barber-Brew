<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(405, [
        'ok' => false,
        'message' => 'Method tidak diizinkan.'
    ]);
}

requireRole(['admin']);

try {
    // Pesanan yang menunggu pembayaran ditampilkan lebih dulu, lalu yang terbaru.
    $stmt = $pdo->query(
        "SELECT
            o.id,
            o.order_code,
            o.total,
            o.status,
            o.payment_status,
            o.created_at,
            u.name AS customer_name,
            u.phone AS customer_phone,
            b.booking_date,
            b.start_time,
            b.end_time,
            b.status AS booking_status,
            bu.name AS barber_name
         FROM orders o
         INNER JOIN users u ON u.id = o.customer_id
         LEFT JOIN bookings b ON b.id = o.booking_id
         LEFT JOIN barber_profiles bp ON bp.id = b.barber_id
         LEFT JOIN users bu ON bu.id = bp.user_id
         ORDER BY (o.payment_status = 'pending') DESC, o.created_at DESC, o.id DESC
         LIMIT 200"
    );

    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $itemsByOrder = [];

    if ($orders) {
        $ids = array_column($orders, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $itemStmt = $pdo->prepare(
            "SELECT order_id, item_name_snapshot, quantity, line_total
             FROM order_items
             WHERE order_id IN ($placeholders)
             ORDER BY id ASC"
        );
        $itemStmt->execute($ids);

        foreach ($itemStmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $itemsByOrder[(int) $item['order_id']][] = [
                'name' => $item['item_name_snapshot'],
                'quantity' => (int) $item['quantity'],
                'line_total' => $item['line_total']
            ];
        }
    }

    foreach ($orders as &$order) {
        $order['id'] = (int) $order['id'];
        $order['items'] = $itemsByOrder[$order['id']] ?? [];
    }
    unset($order);

    jsonResponse(200, [
        'ok' => true,
        'orders' => $orders,
        'csrf_token' => $_SESSION['csrf_token']
    ]);
} catch (Throwable $e) {
    error_log($e->getMessage());

    jsonResponse(500, [
        'ok' => false,
        'message' => 'Gagal mengambil daftar pesanan.'
    ]);
}
