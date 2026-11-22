# Perubahan frontend dan integrasi

- Menambahkan landing page `index.html` dengan layanan dinamis dari API katalog.
- Mengubah `login.html` menjadi form login + registrasi customer yang menggunakan endpoint autentikasi, session cookie, dan CSRF.
- Menambahkan `customer.html` untuk pemesanan café/reservasi barber dan riwayat pesanan.
- Menambahkan `barber.html` untuk daftar reservasi barber dan transisi status yang diizinkan backend.
- Menambahkan `admin.html` terbatas pada konfirmasi pembayaran berdasarkan ID pesanan, sesuai endpoint yang tersedia.
- Menambahkan `public/api/barbers.php` agar frontend dapat memperoleh daftar barber yang tersedia. Endpoint ini hanya membaca barber aktif beserta profilnya.
- Menambahkan CSS bersama dan JavaScript integrasi API.
- Mengganti nama dump SQL agar sesuai dengan instruksi README.
- Memperbarui dokumentasi setup dan batasan fitur.

## Verifikasi yang dilakukan

- `node --check assets/js/app.js`: lolos.
- `php -l` untuk seluruh file PHP: lolos.
- Belum dilakukan uji runtime terhadap MySQL/Laragon dari lingkungan ini. Uji integrasi harus dilakukan setelah folder dijalankan pada Laragon dan database diimpor.


## Perbaikan autentikasi
- Event tombol ganti Login/Daftar dipasang sebelum request token CSRF, sehingga tombol Daftar tidak macet ketika endpoint CSRF gagal.
- Kegagalan mengambil token CSRF kini ditampilkan di halaman dan dicoba kembali saat form dikirim.
- Placeholder dan autocomplete password disesuaikan antara mode login dan registrasi.


## Perbaikan bug (21 Nov 2026)
- `barber_bookings.php` dan `barber_update_booking.php`: menghapus baris kosong/CRLF sebelum `<?php` yang mengirim output sebelum header dan `session_start()` (dapat menyebabkan sesi gagal bila `output_buffering` mati). Menambahkan `declare(strict_types=1)` dan menyeragamkan line ending.
- Menambahkan `public/auth/me.php` untuk memeriksa sesi dan role pengguna.
- `app.js`: halaman customer, barber, dan admin kini memeriksa sesi + role saat dimuat. Tanpa sesi diarahkan ke `login.html?sesi=habis`; role yang salah diarahkan ke dashboard sesuai role.
- `app.js`: respons 401 dari API (selain login) otomatis mengarahkan ke halaman login.
