<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, [
        'ok' => false,
        'message' => 'Method tidak diizinkan.'
    ]);
}

$customer = requireRole(['customer']);
verifyCsrf();

$data = requestData();

$serviceId = !empty($data['service_id']) ? (int) $data['service_id'] : null;
$barberId  = !empty($data['barber_id']) ? (int) $data['barber_id'] : null;
$date      = trim((string) ($data['booking_date'] ?? ''));
$start     = trim((string) ($data['start_time'] ?? ''));
$note      = trim((string) ($data['note'] ?? ''));
$items     = $data['items'] ?? [];

// Konversi DECIMAL database menjadi satuan sen agar perhitungan tidak
// bergantung pada operasi penjumlahan float.
$toCents = static function ($amount): int {
    $amount = (string) $amount;

    if (!preg_match('/^\d{1,10}(\.\d{1,2})?$/', $amount)) {
        throw new DomainException('Format harga tidak valid.');
    }

    [$whole, $decimal] = array_pad(explode('.', $amount, 2), 2, '0');
    $decimal = str_pad($decimal, 2, '0');

    return ((int) $whole * 100) + (int) $decimal;
};

$toMoney = static function (int $cents): string {
    return number_format($cents / 100, 2, '.', '');
};

// Validasi awal
if (!is_array($items)) {
    jsonResponse(422, [
        'ok' => false,
        'message' => 'Format item café tidak valid.'
    ]);
}

if (($serviceId === null) !== ($barberId === null)) {
    jsonResponse(422, [
        'ok' => false,
        'message' => 'Reservasi harus memiliki layanan dan barber.'
    ]);
}

if ($serviceId !== null && ($serviceId < 1 || $barberId < 1)) {
    jsonResponse(422, [
        'ok' => false,
        'message' => 'Layanan atau barber tidak valid.'
    ]);
}

if ($serviceId === null && count($items) === 0) {
    jsonResponse(422, [
        'ok' => false,
        'message' => 'Pesanan harus berisi layanan barber atau produk café.'
    ]);
}

if ($serviceId !== null) {
    if (
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ||
        !preg_match('/^\d{2}:\d{2}$/', $start)
    ) {
        jsonResponse(422, [
            'ok' => false,
            'message' => 'Tanggal dan jam reservasi wajib diisi.'
        ]);
    }
}

if (strlen($note) > 1000) {
    jsonResponse(422, [
        'ok' => false,
        'message' => 'Catatan maksimal 1000 karakter.'
    ]);
}

// Gabungkan produk dengan ID yang sama.
$quantities = [];

foreach ($items as $item) {
    if (!is_array($item)) {
        jsonResponse(422, [
            'ok' => false,
            'message' => 'Format item pesanan tidak valid.'
        ]);
    }

    $pid = filter_var(
        $item['product_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    $qty = filter_var(
        $item['quantity'] ?? null,
        FILTER_VALIDATE_INT
    );

    if ($pid === false || $pid < 1 || $qty === false || $qty < 1) {
        jsonResponse(422, [
            'ok' => false,
            'message' => 'Produk atau jumlah pesanan tidak valid.'
        ]);
    }

    $quantities[$pid] = ($quantities[$pid] ?? 0) + $qty;

    if ($quantities[$pid] > 50) {
        jsonResponse(422, [
            'ok' => false,
            'message' => 'Maksimal 50 unit untuk setiap produk.'
        ]);
    }
}

if (count($quantities) > 30) {
    jsonResponse(422, [
        'ok' => false,
        'message' => 'Maksimal 30 jenis produk per pesanan.'
    ]);
}

try {
    $pdo->beginTransaction();

    $bookingId = null;
    $totalCents = 0;
    $service = null;

    // ==========================================
    // 1. PROSES RESERVASI BARBERSHOP
    // ==========================================

    if ($serviceId !== null) {

        // Mengunci baris barber agar dua transaksi
        // tidak memproses jadwal barber bersamaan.
        $lock = $pdo->prepare(
            'SELECT id
             FROM barber_profiles
             WHERE id = ? AND is_available = 1
             FOR UPDATE'
        );

        $lock->execute([$barberId]);

        if (!$lock->fetch(PDO::FETCH_ASSOC)) {
            throw new DomainException(
                'Barber tidak ditemukan atau sedang tidak tersedia.'
            );
        }

        // Ambil layanan aktif.
        $query = $pdo->prepare(
            'SELECT id, name, price, duration_minutes
             FROM services
             WHERE id = ? AND is_active = 1'
        );

        $query->execute([$serviceId]);
        $service = $query->fetch(PDO::FETCH_ASSOC);

        if (!$service) {
            throw new DomainException('Layanan tidak tersedia.');
        }

        // Validasi tanggal.
        $dateObj = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        if (
            !$dateObj ||
            $dateObj->format('Y-m-d') !== $date ||
            $date < date('Y-m-d')
        ) {
            throw new DomainException(
                'Tanggal reservasi tidak valid atau sudah lewat.'
            );
        }

        // Validasi waktu mulai.
        $startObj = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i',
            $date . ' ' . $start
        );

        if (
            !$startObj ||
            $startObj->format('Y-m-d H:i') !== $date . ' ' . $start
        ) {
            throw new DomainException('Jam reservasi tidak valid.');
        }

        if ($startObj <= new DateTimeImmutable()) {
            throw new DomainException(
                'Jam reservasi harus berada di masa mendatang.'
            );
        }

        // Hitung waktu selesai berdasarkan durasi layanan.
        $duration = (int) $service['duration_minutes'];

        if ($duration < 1) {
            throw new DomainException('Durasi layanan tidak valid.');
        }

        $endObj = $startObj->modify('+' . $duration . ' minutes');

        // Reservasi tidak boleh melewati pergantian hari.
        if ($endObj->format('Y-m-d') !== $date) {
            throw new DomainException(
                'Durasi layanan melewati batas hari kerja.'
            );
        }

        $startSql = $startObj->format('H:i:s');
        $endSql = $endObj->format('H:i:s');

        // Ambil jadwal kerja barber.
        $weekday = (int) $dateObj->format('N');

        $hours = $pdo->prepare(
            'SELECT start_time, end_time, is_day_off
             FROM barber_working_hours
             WHERE barber_id = ? AND weekday = ?'
        );

        $hours->execute([$barberId, $weekday]);
        $work = $hours->fetch(PDO::FETCH_ASSOC);

        if (
            !$work ||
            (int) $work['is_day_off'] === 1 ||
            $startSql < $work['start_time'] ||
            $endSql > $work['end_time']
        ) {
            throw new DomainException(
                'Jam tersebut berada di luar jadwal kerja barber.'
            );
        }

        // Periksa bentrok dengan reservasi lain.
        $overlap = $pdo->prepare(
            "SELECT id
             FROM bookings
             WHERE barber_id = ?
               AND booking_date = ?
               AND status IN ('pending', 'confirmed', 'in_progress')
               AND start_time < ?
               AND end_time > ?
             LIMIT 1"
        );

        $overlap->execute([
            $barberId,
            $date,
            $endSql,
            $startSql
        ]);

        if ($overlap->fetch(PDO::FETCH_ASSOC)) {
            throw new DomainException(
                'Jadwal barber sudah terisi. Silakan pilih waktu lain.'
            );
        }

        $servicePriceCents = $toCents($service['price']);
        $totalCents += $servicePriceCents;

        // Simpan reservasi.
        $insertBooking = $pdo->prepare(
            "INSERT INTO bookings
             (
                customer_id,
                barber_id,
                service_id,
                booking_date,
                start_time,
                end_time,
                service_price_snapshot,
                status,
                customer_note
             )
             VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?)"
        );

        $insertBooking->execute([
            $customer['id'],
            $barberId,
            $serviceId,
            $date,
            $startSql,
            $endSql,
            $toMoney($servicePriceCents),
            $note !== '' ? $note : null
        ]);

        $bookingId = (int) $pdo->lastInsertId();
    }

    // ==========================================
    // 2. PROSES PRODUK CAFÉ
    // ==========================================

    $productRows = [];

    // Urutkan ID untuk menjaga urutan penguncian stok.
    ksort($quantities);

    foreach ($quantities as $pid => $qty) {

        $query = $pdo->prepare(
            'SELECT id, name, price, stock
             FROM cafe_products
             WHERE id = ? AND is_available = 1
             FOR UPDATE'
        );

        $query->execute([$pid]);
        $product = $query->fetch(PDO::FETCH_ASSOC);

        if (!$product || (int) $product['stock'] < $qty) {
            throw new DomainException(
                'Produk tidak tersedia atau stok tidak mencukupi.'
            );
        }

        $unitPriceCents = $toCents($product['price']);
        $lineTotalCents = $unitPriceCents * $qty;

        $totalCents += $lineTotalCents;

        $productRows[] = [
            'product' => $product,
            'quantity' => $qty,
            'unit_price' => $unitPriceCents,
            'line_total' => $lineTotalCents
        ];
    }

    // ==========================================
    // 3. SIMPAN TRANSAKSI UTAMA
    // ==========================================

    $code = 'BB' . date('ymd') . strtoupper(bin2hex(random_bytes(3)));
    $total = $toMoney($totalCents);

    $insertOrder = $pdo->prepare(
        "INSERT INTO orders
         (
            order_code,
            customer_id,
            booking_id,
            subtotal,
            total,
            status,
            payment_status
         )
         VALUES (?, ?, ?, ?, ?, 'awaiting_payment', 'pending')"
    );

    $insertOrder->execute([
        $code,
        $customer['id'],
        $bookingId,
        $total,
        $total
    ]);

    $orderId = (int) $pdo->lastInsertId();

    // ==========================================
    // 4. SIMPAN ITEM LAYANAN
    // ==========================================

    if ($bookingId !== null) {

        $insertService = $pdo->prepare(
            "INSERT INTO order_items
             (
                order_id,
                item_type,
                service_id,
                item_name_snapshot,
                unit_price,
                quantity,
                line_total
             )
             VALUES (?, 'service', ?, ?, ?, 1, ?)"
        );

        $servicePrice = $toMoney($toCents($service['price']));

        $insertService->execute([
            $orderId,
            $serviceId,
            $service['name'],
            $servicePrice,
            $servicePrice
        ]);
    }

    // ==========================================
    // 5. SIMPAN ITEM CAFÉ DAN KURANGI STOK
    // ==========================================

    $insertProduct = $pdo->prepare(
        "INSERT INTO order_items
         (
            order_id,
            item_type,
            product_id,
            item_name_snapshot,
            unit_price,
            quantity,
            line_total
         )
         VALUES (?, 'cafe_product', ?, ?, ?, ?, ?)"
    );

    $updateStock = $pdo->prepare(
        'UPDATE cafe_products
         SET stock = stock - ?
         WHERE id = ? AND stock >= ?'
    );

    foreach ($productRows as $row) {

        $product = $row['product'];
        $qty = $row['quantity'];

        $insertProduct->execute([
            $orderId,
            $product['id'],
            $product['name'],
            $toMoney($row['unit_price']),
            $qty,
            $toMoney($row['line_total'])
        ]);

        $updateStock->execute([
            $qty,
            $product['id'],
            $qty
        ]);

        if ($updateStock->rowCount() !== 1) {
            throw new DomainException(
                'Stok produk berubah. Silakan ulangi pesanan.'
            );
        }
    }

    // ==========================================
    // 6. BUAT DATA PEMBAYARAN
    // ==========================================

    $payment = $pdo->prepare(
        "INSERT INTO payments
         (order_id, method, amount, status)
         VALUES (?, 'cash', ?, 'pending')"
    );

    $payment->execute([
        $orderId,
        $total
    ]);

    // ==========================================
    // 7. SELESAIKAN TRANSAKSI
    // ==========================================

    $pdo->commit();

    jsonResponse(201, [
        'ok' => true,
        'message' => 'Pesanan berhasil dibuat dan menunggu pembayaran.',
        'order' => [
            'id' => $orderId,
            'code' => $code,
            'booking_id' => $bookingId,
            'total' => $total,
            'payment_status' => 'pending'
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
        'message' => 'Pesanan gagal diproses. Tidak ada perubahan yang disimpan.'
    ]);
}
