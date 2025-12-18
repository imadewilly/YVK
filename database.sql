CREATE DATABASE IF NOT EXISTS yvk_store CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE yvk_store;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    role ENUM('admin', 'pelanggan') NOT NULL DEFAULT 'pelanggan',
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS customer_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    address TEXT NOT NULL,
    phone VARCHAR(30) NOT NULL,
    origin VARCHAR(120) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    price DECIMAL(12,2) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    image_url VARCHAR(255),
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_products_admin FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    status ENUM('pending', 'diproses', 'dikirim', 'selesai', 'dibatalkan') NOT NULL DEFAULT 'pending',
    payment_method VARCHAR(50) NOT NULL DEFAULT 'Transfer BCA',
    payment_note VARCHAR(255) DEFAULT 'Rek. BCA 123456789 a.n YVK Store',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_orders_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS order_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    user_id INT NOT NULL,
    customer_name VARCHAR(150) NOT NULL,
    product_name VARCHAR(150) NOT NULL,
    product_price DECIMAL(12,2) NOT NULL,
    quantity INT NOT NULL,
    status ENUM('pending', 'diproses', 'dikirim', 'selesai', 'dibatalkan') NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    payment_note VARCHAR(255),
    order_date DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO users (name, email, role, password_hash) VALUES
('Super Admin', 'admin@yvk.local', 'admin', '$2y$10$jBtonUYdgRTP1zdH1iD0eujxnCLfjwPKpqXK.3QoADk0ddcUbuiBi'),
('Pelanggan Demo', 'pelanggan@yvk.local', 'pelanggan', '$2y$10$jBtonUYdgRTP1zdH1iD0eujxnCLfjwPKpqXK.3QoADk0ddcUbuiBi');

INSERT INTO customer_profiles (user_id, full_name, address, phone, origin) VALUES
(2, 'Pelanggan Demo', 'Jl. Mawar No. 12, Denpasar', '081234567890', 'Denpasar');

INSERT INTO products (name, description, price, stock, image_url, created_by) VALUES
('Kopi Arabika Premium', 'Bijian kopi arabika pilihan pegunungan Kintamani.', 75000, 15, 'https://images.unsplash.com/photo-1447933601403-0c6688de566e?auto=format&fit=crop&w=400', 1),
('Teh Bunga Telang', 'Minuman herbal penenang dengan warna alami ungu.', 45000, 30, 'https://images.unsplash.com/photo-1470337458703-46ad1756a187?auto=format&fit=crop&w=400', 1),
('Keripik Pisang Cokelat', 'Camilan renyah rasa cokelat favorit keluarga.', 28000, 50, 'https://images.unsplash.com/photo-1514996937319-344454492b37?auto=format&fit=crop&w=400', 1);

INSERT INTO orders (user_id, product_id, quantity, status, payment_method, payment_note) VALUES
(2, 1, 1, 'diproses', 'Transfer BCA', 'Rek. BCA 123456789 a.n YVK Store');

-- Password plaintext untuk kedua akun demo di atas adalah: Password123!

