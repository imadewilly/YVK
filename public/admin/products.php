<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
requireAuth(['admin']);

$user = $_SESSION['user'];
$activeTab = 'products';
$pdo = getDatabaseConnection();
$successMessages = [];
$errorMessages = [];

$uploadDir = __DIR__ . '/../uploads/products';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}
$uploadBaseUrl = PUBLIC_PATH . '/uploads/products';
$galleryFiles = [];
$galleryGlob = glob($uploadDir . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE);
if ($galleryGlob) {
    foreach ($galleryGlob as $file) {
        $galleryFiles[] = $uploadBaseUrl . '/' . basename($file);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_product') {
    $name = trim($_POST['name'] ?? '');
    $price = (float) ($_POST['price'] ?? 0);
    $stock = max(0, (int) ($_POST['stock'] ?? 0));
    $description = trim($_POST['description'] ?? '');
    $imageUrl = trim($_POST['image_url'] ?? '');
    $galleryImage = trim($_POST['gallery_image'] ?? '');
    $isPreorder = isset($_POST['is_preorder']) ? 1 : 0; // Checkbox untuk produk Preorder
    $uploadFile = $_FILES['image_file'] ?? null;
    $imageError = '';

    if ($uploadFile && $uploadFile['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($uploadFile['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($uploadFile['name'], PATHINFO_EXTENSION));
            $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
            if (!in_array($ext, $allowedExt, true)) {
                $imageError = 'Format gambar tidak didukung. Gunakan JPG, PNG, atau WEBP.';
            } else {
                $filename = uniqid('prod_', true) . '.' . $ext;
                $targetPath = $uploadDir . '/' . $filename;
                if (move_uploaded_file($uploadFile['tmp_name'], $targetPath)) {
                    $imageUrl = $uploadBaseUrl . '/' . $filename;
                    $galleryFiles[] = $imageUrl;
                } else {
                    $imageError = 'Gagal mengunggah gambar.';
                }
            }
        } else {
            $imageError = 'Gagal mengunggah gambar (kode error: ' . $uploadFile['error'] . ').';
        }
    } elseif ($galleryImage !== '') {
        $imageUrl = $galleryImage;
    }

    if ($name === '' || $price <= 0) {
        $errorMessages[] = 'Nama produk dan harga wajib diisi.';
    } elseif ($imageError) {
        $errorMessages[] = $imageError;
    } else {
        try {
            $stmt = $pdo->prepare('INSERT INTO products (name, description, price, stock, image_url, is_preorder, created_by) VALUES (:name, :description, :price, :stock, :image_url, :is_preorder, :created_by)');
            $stmt->execute([
                'name'        => $name,
                'description' => $description,
                'price'       => $price,
                'stock'       => $stock,
                'image_url'   => $imageUrl,
                'is_preorder' => $isPreorder,
                'created_by'  => $user['id'],
            ]);
            $successMessages[] = 'Produk baru berhasil ditambahkan.';
        } catch (PDOException $e) {
            $errorMessages[] = 'Gagal menambahkan produk.';
            error_log('Create product error: ' . $e->getMessage());
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_product') {
    $productId = (int) ($_POST['product_id'] ?? 0);

    if ($productId > 0) {
        try {
            $stmt = $pdo->prepare('DELETE FROM products WHERE id = :id');
            $stmt->execute(['id' => $productId]);
            $successMessages[] = 'Produk berhasil dihapus.';
        } catch (PDOException $e) {
            $errorMessages[] = 'Gagal menghapus produk.';
            error_log('Delete product error: ' . $e->getMessage());
        }
    } else {
        $errorMessages[] = 'Produk tidak valid.';
    }
}

$productsStmt = $pdo->query('SELECT id, name, price, stock, image_url, created_at FROM products ORDER BY created_at DESC');
$products = $productsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Produk</title>
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
                <a href="<?= PUBLIC_PATH ?>/admin/products.php" class="<?= $activeTab === 'products' ? 'active' : ''; ?>">Produk</a>
                <a href="<?= PUBLIC_PATH ?>/admin/orders.php">Pesanan</a>
                <a href="<?= PUBLIC_PATH ?>/admin/preorder.php">Preorder</a>
                <a href="<?= PUBLIC_PATH ?>/admin/customers.php">Customer</a>
                <a href="<?= PUBLIC_PATH ?>/admin/order_history.php">History</a>
                <a href="<?= PUBLIC_PATH ?>/admin/sales_report.php">Laporan</a>
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
        <?php foreach ($successMessages as $message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message); ?></div>
        <?php endforeach; ?>
        <?php foreach ($errorMessages as $message): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($message); ?></div>
        <?php endforeach; ?>

        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card-elevated h-100">
                    <div class="card-section">
                        <h2 class="section-title mb-3">Tambah Produk Baru</h2>
                        <form method="POST" class="row g-3 admin-form" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="create_product">
                            <div class="col-12">
                                <label class="form-label" style="color: white;">Nama Produk</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" style="color: white;">Harga (Rp)</label>
                                <input type="number" class="form-control" name="price" min="1000" step="500" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" style="color: white;">Stok</label>
                                <input type="number" class="form-control" name="stock" min="0" value="0">
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_preorder" id="is_preorder" value="1">
                                    <label class="form-check-label" for="is_preorder" style="color: white;">
                                        Produk Preorder (Status order langsung menjadi Preorder saat checkout)
                                    </label>
                                </div>
                                <small class="text-muted">Centang jika produk ini adalah Preorder dan tidak bisa langsung dibuat/dikirim</small>
                            </div>
                            <div class="col-12">
                                <label class="form-label" style="color: white;">Deskripsi</label>
                                <textarea class="form-control" rows="3" name="description"></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label" style="color: white;">URL Gambar (opsional)</label>
                                <input type="url" class="form-control" name="image_url">
                            </div>
                            <div class="col-12">
                                <label class="form-label" style="color: white;">Upload Gambar dari Komputer</label>
                                <input type="file" class="form-control" name="image_file" accept=".jpg,.jpeg,.png,.webp">
                                <small class="text-muted">Pilih file dari perangkat Anda.</small>
                            </div>
                            <?php if ($galleryFiles): ?>
                                <div class="col-12">
                                    <label class="form-label" style="color: white;" >Atau pilih dari Galeri</label>
                                    <select class="form-select" name="gallery_image">
                                        <option value="">-- Pilih gambar yang sudah tersedia --</option>
                                        <?php foreach ($galleryFiles as $imagePath): ?>
                                            <option value="<?= htmlspecialchars($imagePath); ?>"><?= htmlspecialchars(basename($imagePath)); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                            <div class="col-12">
                                <button type="submit" class="btn btn-gradient w-100 text-white">Simpan Produk</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card-elevated h-100">
                    <div class="card-section">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h2 class="section-title mb-0">Daftar Produk</h2>
                            <span class="pill pill-info">Total: <?= count($products); ?></span>
                        </div>
                        <?php if (!$products): ?>
                            <p class="text-muted">Belum ada produk.</p>
                        <?php else: ?>
                            <div class="product-grid">
                                <?php foreach ($products as $product): ?>
                                    <div class="product-card">
                                        <?php if ($product['image_url']): ?>
                                            <img src="<?= htmlspecialchars($product['image_url']); ?>" alt="<?= htmlspecialchars($product['name']); ?>">
                                        <?php endif; ?>
                                        <div class="info">
                                            <h5 class="fw-semibold mb-1 text-white"><?= htmlspecialchars($product['name']); ?></h5>
                                            <p class="text-muted mb-2">ID: <?= $product['id']; ?> • Ditambah <?= date('d M Y', strtotime($product['created_at'])); ?></p>
                                            <p class="text-white mb-2">Rp<?= number_format($product['price'], 0, ',', '.'); ?></p>
                                            <div class="d-flex justify-content-between align-items-center gap-2">
                                                <span class="pill pill-warning">Stok: <?= (int) $product['stock']; ?></span>
                                                <div class="d-flex gap-2">
                                                    <a href="<?= PUBLIC_PATH ?>/admin/product_edit.php?id=<?= $product['id']; ?>" class="btn btn-sm btn-outline-light">Edit</a>
                                                    <form method="POST" onsubmit="return confirm('Hapus produk ini?');">
                                                        <input type="hidden" name="action" value="delete_product">
                                                        <input type="hidden" name="product_id" value="<?= $product['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>

