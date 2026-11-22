# Barber & Brew — PHP Native + MySQL

Aplikasi lokal Barber & Brew menggunakan PHP Native, MySQL, PDO, session, password hashing, dan CSRF token. Frontend memakai HTML, Tailwind CSS CDN, dan JavaScript `fetch()`.

## Menjalankan di Laragon
1. Ekstrak folder `barber-brew` ke `C:\laragon\www\barber-brew`.
2. Jalankan Apache dan MySQL melalui Laragon.
3. Buka phpMyAdmin, buat/import database `barber_brew` menggunakan `database/barber_brew.sql`.
4. Periksa `config/database.php`. Konfigurasi bawaan proyek adalah host `127.0.0.1`, port `3307`, database `barber_brew`, user `root`, password kosong. Sesuaikan dengan MySQL lokal Anda.
5. Buka `http://localhost/barber-brew/`.

## Halaman frontend
- `index.html`: landing page dan ringkasan layanan dinamis.
- `login.html`: login dan registrasi pelanggan.
- `customer.html`: membuat pesanan café / reservasi barber dan melihat riwayat pesanan.
- `barber.html`: melihat reservasi milik barber yang login dan memperbarui status pengerjaan.
- `admin.html`: konfirmasi pembayaran tunai berdasarkan ID pesanan.

Halaman internal mengandalkan session PHP. Login akan mengarahkan pengguna sesuai role: customer, barber, atau admin.

## API yang digunakan frontend
- `GET public/api/catalog.php`: daftar layanan, produk café, token CSRF.
- `GET public/api/barbers.php`: daftar barber tersedia (endpoint tambahan untuk mengisi pilihan barber di form booking).
- `GET public/api/my_orders.php`: riwayat pesanan pelanggan.
- `GET public/api/barber_bookings.php`: reservasi milik barber yang login.
- `POST public/auth/register.php`: registrasi customer.
- `POST public/auth/login.php`: login.
- `POST public/auth/logout.php`: logout.
- `POST public/api/create_order.php`: membuat pesanan café dan/atau reservasi.
- `POST public/api/barber_update_booking.php`: memperbarui status reservasi.
- `POST public/api/admin_confirm_payment.php`: konfirmasi pembayaran oleh admin.

Semua request POST mengirim token melalui header `X-CSRF-Token`. Browser mengirim cookie session dengan `credentials: same-origin`.

## Batasan backend saat ini
- Registrasi publik hanya membuat role `customer`.
- Akun barber/admin dan profil/jadwal barber perlu disiapkan melalui database.
- Tidak ada API untuk melihat daftar semua pembayaran/pesanan admin; halaman admin saat ini hanya menerima ID pesanan untuk konfirmasi.
- Pembayaran yang didukung backend saat ini adalah konfirmasi manual/tunai oleh admin, bukan payment gateway atau QRIS otomatis.
- Tidak ada API untuk memeriksa ketersediaan slot secara langsung sebelum submit; validasi jam kerja dan bentrok dilakukan server saat pesanan dibuat.
- Pembatalan/refund, pengelolaan stok dari dashboard admin, CRUD layanan/produk, dan pengaturan jadwal barber melalui UI belum tersedia.

## Catatan keamanan dan deployment
Untuk produksi, arahkan document root web server ke folder `public/` agar `app/`, `config/`, dan `database/` tidak dapat diakses langsung. Jangan memakai kredensial default untuk deployment. Aktifkan HTTPS dan konfigurasi cookie session yang sesuai.
