# YVK Store (Prototype)

Langkah cepat untuk menyiapkan halaman login responsif dengan role Admin dan Pelanggan.

## Setup

1. Import `database.sql` ke MySQL (mis. via phpMyAdmin).  
2. Pastikan kredensial di `config/database.php` sesuai dengan environment lokal Anda.  
3. Jalankan proyek melalui Apache (contoh: `http://localhost/yvk/public/login.php`).  
4. Setelah import, Anda sudah mendapatkan data contoh: admin, pelanggan, 3 produk, dan 1 pesanan.

## Akun Demo

- Admin: `admin@yvk.local` / `Password123!`  
- Pelanggan: `pelanggan@yvk.local` / `Password123!`

## Fitur

- Halaman login responsif dengan tombol daftar pelanggan baru.  
- Halaman pendaftaran berisi Nama Lengkap, Email, Kata Sandi, Alamat, Nomor HP, Asal Tempat.  
- Dashboard pelanggan memiliki ikon keranjang ala marketplace; checkout dilakukan di halaman Keranjang dengan instruksi transfer BCA (rek. 123456789 a.n YVK Store) dan konfirmasi ke 085162798225.  
- Dashboard admin memiliki tiga halaman terpisah:  
  - `Dashboard` untuk ringkasan metrik dan aktivitas terbaru.  
  - `Produk` untuk menambah/daftar produk (via URL, upload lokal, atau galeri).  
  - `Pesanan` untuk melihat dan memperbarui status transaksi pelanggan.  
- Tabel database baru: `customer_profiles`, `products`, `orders` (dilengkapi kolom payment_method & payment_note).

## Struktur Direktori

- `config/` koneksi database.  
- `includes/` helper autentikasi dan session.  
- `public/login.php` halaman masuk + akses ke pendaftaran.  
- `public/register.php` form pendaftaran pelanggan.  
- `public/customer/dashboard.php` interaksi pelanggan (katalog + status pesanan).  
- `public/admin/dashboard.php` kelola produk & pesanan (hanya admin).  
- `database.sql` skema awal + seed user/produk/pesanan.

