-- Migration: Tambahkan field is_preorder ke tabel products
-- File ini menambahkan kolom untuk menandai produk yang bisa langsung Preorder
-- CARA MENJALANKAN:
-- 1. Buka phpMyAdmin (http://localhost/phpmyadmin)
-- 2. Pilih database 'yvk_store' di sidebar kiri
-- 3. Klik tab "SQL" di bagian atas
-- 4. Copy-paste semua isi file ini ke textarea
-- 5. Klik tombol "Go" atau "Jalankan"

USE yvk_store;

-- Tambahkan kolom is_preorder ke tabel products
-- Jika produk is_preorder = 1, maka saat checkout status langsung menjadi 'preorder'
-- Jika produk is_preorder = 0, maka saat checkout status menjadi 'pending' (menunggu)
ALTER TABLE products 
ADD COLUMN is_preorder TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = Produk Preorder, 0 = Produk Ready Stock' AFTER stock;

-- Selesai! Sekarang produk bisa ditandai sebagai Preorder.

