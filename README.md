# KopiKita

Aplikasi e-commerce penjualan kopi (pembeli dan penjual) dengan PHP + MySQL.

## Isi
- `index.html`: tampilan pembeli dan penjual (satu halaman, peran ditentukan saat login)
- `api.php`: API backend (registrasi, login, katalog, keranjang, checkout, pesanan, kelola produk)
- `schema.sql`: struktur database dan data contoh
- `config.example.php`: contoh konfigurasi database

## Menjalankan (XAMPP/Laragon)
1. Salin folder ini ke `htdocs/kopikita` (XAMPP) atau `www/kopikita` (Laragon).
2. Salin `config.example.php` menjadi `config.php`, lalu sesuaikan user dan password MySQL.
3. Jalankan `schema.sql` di phpMyAdmin (tab SQL).
4. Buka `http://localhost/kopikita/index.html`.

## Akun contoh
- Pembeli: `jihan123@gmail.com` / `Jihan123`
- Penjual: `toko@kopikita.id` / `Toko123`

Catatan: GitHub Pages hanya mendukung file statis, jadi PHP tidak berjalan di sana.
