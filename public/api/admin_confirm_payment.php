<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

// Pastikan request menggunakan POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, [
        'ok' => false,
        'message' => 'Method tidak diizinkan.'
    ]);
}

// Hanya admin yang boleh mengonfirmasi pembayaran.
$admin = requireRole(['admin']);

verifyCsrf();

$data = requestData();
$orderId = filter_var(
    $data['order_id'] ?? null,
    FILTER_VALIDATE_INT
);
$orderCode = strtoupper(trim((string) ($data['order_code'] ?? '')));

$byCode = $orderCode !== '';

if ($byCode) {
    if (!preg_match('/^[A-Z0-9]{4,32}$/', $orderCode)) {
        jsonResponse(422, [
            'ok' => false,
            'message' => 'Kode pesanan tidak valid.'
        ]);
    }
} elseif ($orderId === false || $orderId < 1) {
    jsonResponse(422, [
        'ok' => false,
        'message' => 'ID atau kode pesanan tidak valid.'
    ]);
}

// Konversi DECIMAL menjadi satuan sen.
$toCents = static function ($amount): int {
    $amount = (string) $amount;

    if (!preg_match('/^\d{1,10}(\.\d{1,2})?$/', $amount)) {
        throw new DomainException('Nominal pembayaran tidak valid.');
    }

    [$whole, $decimal] = array_pad(explode('.', $amount, 2), 2, '0');

    return ((int) $whole * 100)
        + (int) str_pad($decimal, 2, '0');
};

try {
    $pdo->beginTransaction();

    // 1. Kunci dan periksa pesanan.
    $query = $pdo->prepare(
        'SELECT id, booking_id, total, status, payment_status
         FROM orders
         WHERE ' . ($byCode ? 'order_code' : 'id') . ' = ?
         FOR UPDATE'
    );

    $query->execute([$byCode ? $orderCode : $orderId]);
    $order = $query->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new DomainException('Pesanan tidak ditemukan.');
    }

    $orderId = (int) $order['id'];

    if (
        $order['payment_status'] !== 'pending' ||
        $order['status'] !== 'awaiting_payment'
    ) {
        throw new DomainException(
            'Pesanan tidak sedang menunggu pembayaran.'
        );
    }

    // 2. Periksa data pembayaran.
    $queryPayment = $pdo->prepare(
        'SELECT id, amount, status
         FROM payments
         WHERE order_id = ?
         FOR UPDATE'
    );

    $queryPayment->execute([$orderId]);
    $payments = $queryPayment->fetchAll(PDO::FETCH_ASSOC);

    // Sistem saat ini membuat satu pembayaran untuk satu pesanan.
    if (count($payments) !== 1) {
        throw new DomainException(
            'Data pembayaran tidak ditemukan atau tidak valid.'
        );
    }

    $payment = $payments[0];

    if ($payment['status'] !== 'pending') {
        throw new DomainException(
            'Pembayaran sudah diproses sebelumnya.'
        );
    }

    // 3. Pastikan nominal pembayaran sesuai total pesanan.
    if ($toCents($payment['amount']) !== $toCents($order['total'])) {
        throw new DomainException(
            'Nominal pembayaran tidak sesuai dengan total pesanan.'
        );
    }

    // 4. Jika pesanan memiliki booking, periksa reservasinya.
    if ($order['booking_id'] !== null) {
        $queryBooking = $pdo->prepare(
            'SELECT id, status
             FROM bookings
             WHERE id = ?
             FOR UPDATE'
        );

        $queryBooking->execute([$order['booking_id']]);
        $booking = $queryBooking->fetch(PDO::FETCH_ASSOC);

        if (!$booking || $booking['status'] !== 'pending') {
            throw new DomainException(
                'Reservasi tidak ditemukan atau tidak dapat dikonfirmasi.'
            );
        }

        $updateBooking = $pdo->prepare(
            "UPDATE bookings
             SET status = 'confirmed'
             WHERE id = ? AND status = 'pending'"
        );

        $updateBooking->execute([$order['booking_id']]);

        if ($updateBooking->rowCount() !== 1) {
            throw new DomainException(
                'Gagal mengonfirmasi reservasi.'
            );
        }
    }

    // 5. Perbarui status pembayaran.
    $updatePayment = $pdo->prepare(
        "UPDATE payments
         SET status = 'paid',
             paid_at = NOW()
         WHERE id = ? AND status = 'pending'"
    );

    $updatePayment->execute([$payment['id']]);

    if ($updatePayment->rowCount() !== 1) {
        throw new DomainException(
            'Gagal memperbarui status pembayaran.'
        );
    }

    // 6. Perbarui status pesanan.
    $updateOrder = $pdo->prepare(
        "UPDATE orders
         SET payment_status = 'paid',
             status = 'paid'
         WHERE id = ?
           AND payment_status = 'pending'
           AND status = 'awaiting_payment'"
    );

    $updateOrder->execute([$orderId]);

    if ($updateOrder->rowCount() !== 1) {
        throw new DomainException(
            'Gagal memperbarui status pesanan.'
        );
    }

    // 7. Simpan seluruh perubahan.
    $pdo->commit();

    jsonResponse(200, [
        'ok' => true,
        'message' => 'Pembayaran berhasil dikonfirmasi.',
        'order' => [
            'id' => (int) $orderId,
            'status' => 'paid',
            'payment_status' => 'paid',
            'booking_status' => $order['booking_id'] !== null
                ? 'confirmed'
                : null
        ]
    ]);
} catch (DomainException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    jsonResponse(422, [
        'ok' => false,
        'message' => $e->getMessage()
    ]);
} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());

    jsonResponse(500, [
        'ok' => false,
        'message' => 'Konfirmasi pembayaran gagal.'
    ]);
}
