<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, [
        'ok' => false,
        'message' => 'Method tidak diizinkan.'
    ]);
}

$user = requireRole(['barber']);
verifyCsrf();

$data = requestData();

$bookingId = filter_var(
    $data['booking_id'] ?? null,
    FILTER_VALIDATE_INT
);

$newStatus = trim((string) ($data['status'] ?? ''));

if (!$bookingId || $bookingId < 1) {
    jsonResponse(422, [
        'ok' => false,
        'message' => 'ID reservasi tidak valid.'
    ]);
}

$allowedTransitions = [
    'confirmed' => 'in_progress',
    'in_progress' => 'completed'
];

$orderStatusMap = [
    'in_progress' => 'processing',
    'completed' => 'completed'
];

try {
    $pdo->beginTransaction();

    // Ambil reservasi dan pesanan milik barber yang login.
    $stmt = $pdo->prepare(
        "SELECT
            b.id,
            b.status AS booking_status,
            o.id AS order_id,
            o.status AS order_status
         FROM bookings b
         INNER JOIN barber_profiles bp
            ON bp.id = b.barber_id
         INNER JOIN orders o
            ON o.booking_id = b.id
         WHERE b.id = ?
           AND bp.user_id = ?
         FOR UPDATE"
    );

    $stmt->execute([$bookingId, $user['id']]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        $pdo->rollBack();

        jsonResponse(404, [
            'ok' => false,
            'message' => 'Reservasi atau pesanan tidak ditemukan.'
        ]);
    }

    $currentStatus = $booking['booking_status'];

    if (
        !isset($allowedTransitions[$currentStatus]) ||
        $allowedTransitions[$currentStatus] !== $newStatus
    ) {
        $pdo->rollBack();

        jsonResponse(422, [
            'ok' => false,
            'message' => 'Perubahan status tidak diizinkan.',
            'current_status' => $currentStatus,
            'requested_status' => $newStatus
        ]);
    }

    // Pastikan pesanan sudah dibayar.
    $expectedOrderStatus = $currentStatus === 'confirmed'
        ? 'paid'
        : 'processing';

    if ($booking['order_status'] !== $expectedOrderStatus) {
        $pdo->rollBack();

        jsonResponse(422, [
            'ok' => false,
            'message' => 'Status pesanan tidak sesuai dengan status reservasi.'
        ]);
    }

    // Update status reservasi.
    $updateBooking = $pdo->prepare(
        "UPDATE bookings SET status = ? WHERE id = ?"
    );

    $updateBooking->execute([$newStatus, $bookingId]);

    // Sinkronkan status pesanan.
    $updateOrder = $pdo->prepare(
        "UPDATE orders SET status = ? WHERE id = ?"
    );

    $updateOrder->execute([
        $orderStatusMap[$newStatus],
        $booking['order_id']
    ]);

    $pdo->commit();

    jsonResponse(200, [
        'ok' => true,
        'message' => $newStatus === 'in_progress'
            ? 'Pengerjaan reservasi dimulai.'
            : 'Pengerjaan reservasi selesai.',
        'booking' => [
            'id' => (int) $bookingId,
            'status' => $newStatus
        ],
        'order' => [
            'id' => (int) $booking['order_id'],
            'status' => $orderStatusMap[$newStatus]
        ]
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());

    jsonResponse(500, [
        'ok' => false,
        'message' => 'Gagal memperbarui status reservasi.'
    ]);
}
