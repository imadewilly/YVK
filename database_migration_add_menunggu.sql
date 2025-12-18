-- Migration: Tambahkan status 'menunggu' ke ENUM di tabel orders dan order_history
-- Jalankan file ini di phpMyAdmin atau MySQL client

USE yvk_store;

-- Update ENUM di tabel orders: tambahkan 'menunggu' dan ubah default
ALTER TABLE orders 
MODIFY COLUMN status ENUM('pending', 'menunggu', 'diproses', 'dikirim', 'selesai', 'dibatalkan') NOT NULL DEFAULT 'menunggu';

-- Update ENUM di tabel order_history: tambahkan 'menunggu'
ALTER TABLE order_history 
MODIFY COLUMN status ENUM('pending', 'menunggu', 'diproses', 'dikirim', 'selesai', 'dibatalkan') NOT NULL;

-- Optional: Update data lama yang masih 'pending' menjadi 'menunggu' (jika ingin konsisten)
-- UPDATE orders SET status = 'menunggu' WHERE status = 'pending';
-- UPDATE order_history SET status = 'menunggu' WHERE status = 'pending';

