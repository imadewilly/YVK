-- Migration: Tambahkan status 'preorder' ke ENUM di tabel orders dan order_history
-- File ini aman dijalankan meskipun 'menunggu' belum ada di ENUM
-- CARA MENJALANKAN:
-- 1. Buka phpMyAdmin (http://localhost/phpmyadmin)
-- 2. Pilih database 'yvk_store' di sidebar kiri
-- 3. Klik tab "SQL" di bagian atas
-- 4. Copy-paste semua isi file ini ke textarea
-- 5. Klik tombol "Go" atau "Jalankan"
-- 6. Pastikan tidak ada error (harus muncul "2 rows affected" atau pesan sukses)

USE yvk_store;

-- Update ENUM di tabel orders: tambahkan 'preorder' (dan 'menunggu' jika belum ada)
-- Jika 'menunggu' sudah ada, tidak masalah, tetap aman
ALTER TABLE orders 
MODIFY COLUMN status ENUM('pending', 'menunggu', 'diproses', 'preorder', 'dikirim', 'selesai', 'dibatalkan') NOT NULL DEFAULT 'pending';

-- Update ENUM di tabel order_history: tambahkan 'preorder' (dan 'menunggu' jika belum ada)
ALTER TABLE order_history 
MODIFY COLUMN status ENUM('pending', 'menunggu', 'diproses', 'preorder', 'dikirim', 'selesai', 'dibatalkan') NOT NULL;

-- Selesai! Status 'preorder' sekarang bisa digunakan di aplikasi.

