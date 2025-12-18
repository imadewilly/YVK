# Panduan Deployment YVK Store

## Persiapan untuk Hosting

### 1. File yang Perlu Diperbarui

#### A. Konfigurasi Database (`config/database.php`)
Update dengan kredensial database dari hosting Anda:
```php
$host = 'localhost'; // atau hostname dari hosting
$db   = 'nama_database_dari_hosting';
$user = 'username_database_dari_hosting';
$pass = 'password_database_dari_hosting';
```

#### B. Konfigurasi Path (`config/app.php`)
Update `BASE_PATH` sesuai dengan struktur hosting:
```php
// Jika di root domain: define('BASE_PATH', '');
// Jika di subfolder: define('BASE_PATH', '/nama_folder');
define('BASE_PATH', '/yvk'); // Sesuaikan dengan hosting Anda
define('PUBLIC_PATH', BASE_PATH . '/public');
```

### 2. Upload File ke Hosting

**File yang HARUS diupload:**
- Folder `config/` (semua file)
- Folder `includes/` (semua file)
- Folder `public/` (semua file dan subfolder)
- File `.htaccess` (di root dan di folder public)

**File yang TIDAK perlu diupload:**
- Folder `database*.sql` (hanya untuk migration, jalankan via phpMyAdmin)
- File `README.md`
- File `DEPLOYMENT_GUIDE.md`

### 3. Setup Database di Hosting

1. **Buat Database:**
   - Login ke cPanel atau hosting panel
   - Buat database baru (contoh: `yvk_store`)
   - Buat user database dan berikan akses ke database tersebut

2. **Import Database:**
   - Buka phpMyAdmin di hosting
   - Pilih database yang baru dibuat
   - Klik tab "Import"
   - Upload file `database.sql`
   - Klik "Go" untuk import

3. **Jalankan Migration:**
   - Setelah import, jalankan migration SQL berikut (via phpMyAdmin SQL tab):
     - `database_migration_add_preorder_safe.sql`
     - `database_migration_add_preorder_product.sql`
     - `database_triggers.sql` (opsional, untuk sinkronisasi otomatis)

### 4. Konfigurasi Folder Permissions

Set folder `public/uploads/products/` agar bisa di-write:
- Permission: `755` atau `777` (jika diperlukan)
- Di cPanel: File Manager → Right click folder → Change Permissions

### 5. Testing Setelah Deployment

1. **Test Login:**
   - Buka: `https://yourdomain.com/yvk/public/login.php`
   - Login dengan akun admin default:
     - Email: `admin@yvk.local`
     - Password: `Password123!`

2. **Test Fitur:**
   - Tambah produk
   - Upload gambar produk
   - Buat pesanan (customer)
   - Ubah status pesanan (admin)

### 6. Rekomendasi Hosting Provider

**Untuk PHP/MySQL:**
- **Niagahoster** - Mudah digunakan, support PHP 8.x
- **Hostinger** - Murah, cepat, support PHP 8.x
- **Domainesia** - Lokal Indonesia, support baik
- **Cloudways** - Cloud hosting, lebih advanced

**Requirements:**
- PHP 7.4 atau lebih tinggi (disarankan PHP 8.0+)
- MySQL 5.7 atau lebih tinggi
- Apache dengan mod_rewrite enabled
- Minimum 100MB storage
- Support untuk `.htaccess`

### 7. Security Checklist

- [ ] Ganti password admin default setelah deployment
- [ ] Pastikan `config/database.php` tidak bisa diakses langsung
- [ ] Enable HTTPS/SSL (gratis via Let's Encrypt)
- [ ] Disable error display di production (di `public/.htaccess`)
- [ ] Backup database secara berkala
- [ ] Update PHP ke versi terbaru yang didukung hosting

### 8. Troubleshooting

**Error: Database connection failed**
- Cek kredensial database di `config/database.php`
- Pastikan database sudah dibuat dan user memiliki akses

**Error: 404 Not Found**
- Cek `.htaccess` sudah diupload
- Pastikan mod_rewrite enabled di Apache
- Cek `BASE_PATH` di `config/app.php` sesuai struktur hosting

**Error: Permission denied (upload gambar)**
- Set permission folder `public/uploads/products/` ke 755 atau 777

**Error: Status preorder tidak bisa digunakan**
- Pastikan migration SQL sudah dijalankan di hosting

### 9. Support

Jika ada masalah saat deployment, cek:
1. Error log di hosting (biasanya di cPanel → Error Log)
2. PHP error log
3. Pastikan semua file sudah terupload dengan benar

---

**Catatan:** Setelah deployment, pastikan untuk mengubah password admin default dan melakukan backup database secara berkala.

