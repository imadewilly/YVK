-- Migration: Tambahkan status 'preorder' ke ENUM di tabel orders dan order_history
-- Jalankan file ini di phpMyAdmin atau MySQL client untuk memisahkan Preorder dengan Diproses
-- CARA MENJALANKAN:
-- 1. Buka phpMyAdmin
-- 2. Pilih database 'yvk_store'
-- 3. Klik tab "SQL"
-- 4. Copy-paste semua isi file ini
-- 5. Klik "Go" atau "Jalankan"

USE yvk_store;

-- Update ENUM di tabel orders: tambahkan 'preorder' sebagai status terpisah
-- Jika error karena 'menunggu' belum ada, jalankan dulu database_migration_add_menunggu.sql
ALTER TABLE orders 
MODIFY COLUMN status ENUM('pending', 'menunggu', 'diproses', 'preorder', 'dikirim', 'selesai', 'dibatalkan') NOT NULL DEFAULT 'pending';

-- Update ENUM di tabel order_history: tambahkan 'preorder' sebagai status terpisah
ALTER TABLE order_history 
MODIFY COLUMN status ENUM('pending', 'menunggu', 'diproses', 'preorder', 'dikirim', 'selesai', 'dibatalkan') NOT NULL;

-- Verifikasi: Cek apakah status sudah berhasil ditambahkan
-- SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_SCHEMA = 'yvk_store' AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'status';

