<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(405, [
        'ok' => false,
        'message' => 'Method tidak diizinkan.'
    ]);
}

$user = requireRole(['customer']);

try {
    // 1. Ambil seluruh pesanan milik pelanggan yang sedang login.
    $stmt = $pdo->prepare(
        "SELECT
            o.id,
            o.order_code,
            o.subtotal,
            o.total,
            o.status,
            o.payment_status,
            o.created_at,
            b.booking_date,
            b.start_time,
            b.end_time,
            b.status AS booking_status,
            bpUser.name AS barber_name
         FROM orders o
         LEFT JOIN bookings b ON b.id = o.booking_id
         LEFT JOIN barber_profiles bp ON bp.id = b.barber_id
         LEFT JOIN users bpUser ON bpUser.id = bp.user_id
         WHERE o.customer_id = ?
         ORDER BY o.created_at DESC"
    );

    $stmt->execute([$user['id']]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Jika belum memiliki pesanan.
    if (empty($orders)) {
        jsonResponse(200, [
            'ok' => true,
            'message' => 'Belum ada riwayat pesanan.',
            'orders' => []
        ]);
    }

    // 2. Ambil ID seluruh pesanan.
    $orderIds = array_column($orders, 'id');

    // Placeholder dibuat berdasarkan jumlah ID, bukan input pengguna.
    $placeholders = implode(
        ',',
        array_fill(0, count($orderIds), '?')
    );

    // 3. Ambil seluruh detail item dalam satu query.
    $itemQuery = $pdo->prepare(
        "SELECT
            order_id,
            item_type,
            item_name_snapshot,
            unit_price,
            quantity,
            line_total
         FROM order_items
         WHERE order_id IN ($placeholders)
         ORDER BY id ASC"
    );

    $itemQuery->execute($orderIds);
    $allItems = $itemQuery->fetchAll(PDO::FETCH_ASSOC);

    // 4. Kelompokkan item berdasarkan pesanan.
    $itemsByOrder = [];

    foreach ($allItems as $item) {
        $orderId = (int) $item['order_id'];

        $itemsByOrder[$orderId][] = [
            'type' => $item['item_type'],
            'name' => $item['item_name_snapshot'],
            'unit_price' => $item['unit_price'],
            'quantity' => (int) $item['quantity'],
            'line_total' => $item['line_total']
        ];
    }

    // 5. Gabungkan detail item ke masing-masing pesanan.
    foreach ($orders as &$order) {
        $orderId = (int) $order['id'];

        $order['id'] = $orderId;
        $order['items'] = $itemsByOrder[$orderId] ?? [];

        $order['booking'] = null;

        if ($order['booking_date'] !== null) {
            $order['booking'] = [
                'date' => $order['booking_date'],
                'start_time' => $order['start_time'],
                'end_time' => $order['end_time'],
                'status' => $order['booking_status'],
                'barber_name' => $order['barber_name']
            ];
        }

        unset(
            $order['booking_date'],
            $order['start_time'],
            $order['end_time'],
            $order['booking_status'],
            $order['barber_name']
        );
    }

    unset($order);

    jsonResponse(200, [
        'ok' => true,
        'message' => 'Riwayat pesanan berhasil diambil.',
        'orders' => $orders
    ]);
} catch (Throwable $e) {
    error_log($e->getMessage());

    jsonResponse(500, [
        'ok' => false,
        'message' => 'Gagal mengambil riwayat pesanan.'
    ]);
}
