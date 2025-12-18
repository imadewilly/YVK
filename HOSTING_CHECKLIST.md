# Checklist Persiapan Hosting YVK Store

## Sebelum Upload ke Hosting

### ✅ File Konfigurasi
- [ ] Update `config/database.php` dengan kredensial hosting
- [ ] Update `config/app.php` dengan BASE_PATH yang benar
- [ ] File `.htaccess` sudah dibuat di root
- [ ] File `public/.htaccess` sudah dibuat

### ✅ Database
- [ ] File `database.sql` siap untuk diimport
- [ ] File migration SQL siap:
  - [ ] `database_migration_add_preorder_safe.sql`
  - [ ] `database_migration_add_preorder_product.sql`
  - [ ] `database_triggers.sql` (opsional)

### ✅ Folder Structure
- [ ] Folder `public/uploads/products/` ada dan bisa di-write
- [ ] Semua file PHP sudah diupload
- [ ] File CSS dan assets sudah diupload

## Setelah Upload ke Hosting

### ✅ Database Setup
- [ ] Database sudah dibuat di hosting
- [ ] User database sudah dibuat dan diberikan akses
- [ ] File `database.sql` sudah diimport
- [ ] Migration SQL sudah dijalankan
- [ ] Trigger database sudah dibuat (jika menggunakan)

### ✅ Testing
- [ ] Login admin berhasil
- [ ] Login customer berhasil
- [ ] Tambah produk berhasil
- [ ] Upload gambar produk berhasil
- [ ] Checkout pesanan berhasil
- [ ] Ubah status pesanan berhasil
- [ ] Export Excel/PDF berhasil

### ✅ Security
- [ ] Password admin default sudah diganti
- [ ] HTTPS/SSL sudah diaktifkan
- [ ] Error display sudah dimatikan di production
- [ ] File sensitif tidak bisa diakses langsung

## Informasi Hosting yang Diperlukan

1. **Database Host:** (biasanya `localhost`)
2. **Database Name:** 
3. **Database Username:** 
4. **Database Password:** 
5. **Base Path:** (root domain atau subfolder)
6. **PHP Version:** (minimal 7.4, disarankan 8.0+)

---

**Tips:** Simpan informasi hosting di tempat yang aman dan jangan commit ke Git!

