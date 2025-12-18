<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
requireAuth(['admin']);

$user = $_SESSION['user'];
$pdo = getDatabaseConnection();

$productId = (int) ($_GET['id'] ?? 0);
$success = '';
$error = '';

if ($productId <= 0) {
    die('Produk tidak ditemukan.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $price = (float) ($_POST['price'] ?? 0);
    $stock = max(0, (int) ($_POST['stock'] ?? 0));
    $description = trim($_POST['description'] ?? '');
    $imageUrl = trim($_POST['image_url'] ?? '');
    $isPreorder = isset($_POST['is_preorder']) ? 1 : 0; // Checkbox untuk produk Preorder

    if ($name === '' || $price <= 0) {
        $error = 'Nama dan harga produk wajib diisi.';
    } else {
        try {
            $stmt = $pdo->prepare('
                UPDATE products
                SET name = :name, description = :description, price = :price, stock = :stock, image_url = :image_url, is_preorder = :is_preorder
                WHERE id = :id
            ');
            $stmt->execute([
                'name'        => $name,
                'description' => $description,
                'price'       => $price,
                'stock'       => $stock,
                'image_url'   => $imageUrl,
                'is_preorder' => $isPreorder,
                'id'          => $productId,
            ]);
            $success = 'Produk berhasil diperbarui.';
        } catch (PDOException $e) {
            $error = 'Gagal memperbarui produk.';
            error_log('Update product error: ' . $e->getMessage());
        }
    }
}

$stmt = $pdo->prepare('SELECT id, name, description, price, stock, image_url, is_preorder FROM products WHERE id = :id');
$stmt->execute(['id' => $productId]);
$product = $stmt->fetch();

if (!$product) {
    die('Produk tidak ditemukan.');
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Produk</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= PUBLIC_PATH ?>/assets/css/style.css">
</head>
<body class="app-shell">
<header class="app-navbar">
    <div class="container d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="brand">Panel Admin YVK</div>
            <div class="navbar-tabs">
                <a href="<?= PUBLIC_PATH ?>/admin/dashboard.php">Dashboard</a>
                <a href="<?= PUBLIC_PATH ?>/admin/products.php" class="active">Produk</a>
                <a href="<?= PUBLIC_PATH ?>/admin/orders.php">Pesanan</a>
                <a href="<?= PUBLIC_PATH ?>/admin/preorder.php">Preorder</a>
                <a href="<?= PUBLIC_PATH ?>/admin/customers.php">Customer</a>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3 text-white">
            <span>Halo, <?= htmlspecialchars($user['name']); ?></span>
            <a class="btn btn-outline-light btn-sm" href="<?= PUBLIC_PATH ?>/logout.php">Keluar</a>
        </div>
    </div>
</header>
<main class="app-content">
    <div class="container">
        <div class="card-elevated">
            <div class="card-section">
                <h2 class="section-title mb-3">Edit Produk #<?= (int)$product['id']; ?></h2>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success); ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" class="row g-3 admin-form">
                    <div class="col-12">
                        <label class="form-label text-white">Nama Produk</label>
                        <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($product['name']); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-white">Harga (Rp)</label>
                        <input type="number" name="price" class="form-control" min="1000" step="500" required value="<?= htmlspecialchars($product['price']); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-white">Stok</label>
                        <input type="number" name="stock" class="form-control" min="0" value="<?= (int)$product['stock']; ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-white">URL Gambar</label>
                        <input type="url" name="image_url" class="form-control" value="<?= htmlspecialchars($product['image_url']); ?>">
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_preorder" id="is_preorder" value="1" <?= (!empty($product['is_preorder'])) ? 'checked' : ''; ?>>
                            <label class="form-check-label text-white" for="is_preorder">
                                Produk Preorder (Status order langsung menjadi Preorder saat checkout)
                            </label>
                        </div>
                        <small class="text-muted">Centang jika produk ini adalah Preorder dan tidak bisa langsung dibuat/dikirim</small>
                    </div>
                    <div class="col-12">
                        <label class="form-label text-white">Deskripsi</label>
                        <textarea name="description" rows="4" class="form-control"><?= htmlspecialchars($product['description']); ?></textarea>
                    </div>
                    <div class="col-12 d-flex justify-content-between">
                        <a href="<?= PUBLIC_PATH ?>/admin/products.php" class="btn btn-outline-light">Kembali</a>
                        <button type="submit" class="btn btn-gradient text-white">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>
</body>
</html>


