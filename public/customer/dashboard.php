<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
requireAuth(['pelanggan', 'admin']);

$user = $_SESSION['user'];
$pdo = getDatabaseConnection();
$success = '';
$error = '';
$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
$paymentInfo = 'Transfer ke BCA 123456789 a.n YVK Store';
$statusLabels = [
    'menunggu'   => 'Menunggu',
    'pending'    => 'Menunggu', // Backward compatibility
    'diproses'   => 'Diproses',
    'preorder'   => 'Preorder',
    'dikirim'    => 'Dikirim',
    'selesai'    => 'Selesai',
    'dibatalkan' => 'Dibatalkan',
];

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
if (!isset($_SESSION['cart'][$user['id']])) {
    $_SESSION['cart'][$user['id']] = [];
}
$cart = &$_SESSION['cart'][$user['id']];
$cartCount = array_sum(array_column($cart, 'quantity'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_to_cart') {
        $productId = (int) ($_POST['product_id'] ?? 0);
        $quantity = max(1, (int) ($_POST['quantity'] ?? 1));

        $stmt = $pdo->prepare('SELECT id, name, stock FROM products WHERE id = :id');
        $stmt->execute(['id' => $productId]);
        $product = $stmt->fetch();

        if (!$product) {
            $error = 'Produk tidak ditemukan.';
        } elseif ($product['stock'] <= 0) {
            $error = 'Stok produk habis.';
        } else {
            $currentQty = $cart[$productId]['quantity'] ?? 0;
            $allowedQty = min($product['stock'], $currentQty + $quantity);
            $cart[$productId] = [
                'quantity' => $allowedQty,
            ];
            $success = $product['name'] . ' ditambahkan ke keranjang.';
        }
    } elseif ($action === 'cancel_order') {
        $orderId = (int) ($_POST['order_id'] ?? 0);

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('
                SELECT o.id, o.status, o.quantity, o.product_id, p.name AS product_name
                FROM orders o
                JOIN products p ON p.id = o.product_id
                WHERE o.id = :id AND o.user_id = :user_id
                FOR UPDATE
            ');
            $stmt->execute(['id' => $orderId, 'user_id' => $user['id']]);
            $order = $stmt->fetch();

            if (!$order) {
                throw new RuntimeException('Pesanan tidak ditemukan.');
            }

            if (!in_array($order['status'], ['pending', 'menunggu', 'diproses', 'preorder'], true)) {
                throw new RuntimeException('Pesanan tidak dapat dibatalkan pada status saat ini.');
            }

            $updateOrder = $pdo->prepare('UPDATE orders SET status = :status WHERE id = :id');
            $updateOrder->execute(['status' => 'dibatalkan', 'id' => $orderId]);

            // Update order_history juga (trigger akan handle ini, tapi kita update manual untuk memastikan)
            $updateHistory = $pdo->prepare('UPDATE order_history SET status = :status WHERE order_id = :order_id');
            $updateHistory->execute(['status' => 'dibatalkan', 'order_id' => $orderId]);

            $restock = $pdo->prepare('UPDATE products SET stock = stock + :qty WHERE id = :id');
            $restock->execute(['qty' => $order['quantity'], 'id' => $order['product_id']]);

            $pdo->commit();
            $success = 'Pesanan ' . $order['product_name'] . ' berhasil dibatalkan.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e instanceof RuntimeException ? $e->getMessage() : 'Gagal membatalkan pesanan.';
            error_log('Cancel order error: ' . $e->getMessage());
        }
    }

    if ($success !== '') {
        $_SESSION['flash_success'] = $success;
    }
    if ($error !== '') {
        $_SESSION['flash_error'] = $error;
    }
    header('Location: ' . PUBLIC_PATH . '/customer/dashboard.php');
    exit;
}

$cartCount = array_sum(array_column($cart, 'quantity'));

// Sinkronisasi order_history dengan orders (memastikan semua status selalu update)
try {
    $syncStmt = $pdo->prepare('
        UPDATE order_history
        INNER JOIN orders ON order_history.order_id = orders.id
        SET order_history.status = orders.status
        WHERE order_history.status != orders.status
    ');
    $syncStmt->execute();
} catch (PDOException $e) {
    // Ignore sync errors, log untuk debugging
    error_log('Sync order_history error: ' . $e->getMessage());
}

$productsStmt = $pdo->query('SELECT id, name, description, price, stock, image_url, is_preorder FROM products ORDER BY created_at DESC');
$products = $productsStmt->fetchAll();

$ordersStmt = $pdo->prepare('
    SELECT o.id, o.quantity, o.status, o.payment_method, o.payment_note, o.created_at,
           p.name AS product_name, p.price
    FROM orders o
    JOIN products p ON p.id = o.product_id
    WHERE o.user_id = :user_id
    ORDER BY o.created_at DESC
');
$ordersStmt->execute(['user_id' => $user['id']]);
$orders = $ordersStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Pelanggan</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= PUBLIC_PATH ?>/assets/css/style.css">
</head>
<body class="app-shell">
<header class="app-navbar">
    <div class="container d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="brand">YVK Store</div>
            <div class="navbar-tabs">
                <a href="<?= PUBLIC_PATH ?>/customer/dashboard.php" class="active">Katalog</a>
                <a href="<?= PUBLIC_PATH ?>/customer/cart.php">Keranjang</a>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3 text-white">
            <a href="<?= PUBLIC_PATH ?>/customer/cart.php" class="cart-btn position-relative">
                <span class="cart-icon">🛒</span>
                <?php if ($cartCount): ?>
                    <span class="badge bg-danger position-absolute top-0 start-100 translate-middle rounded-pill"><?= $cartCount; ?></span>
                <?php endif; ?>
            </a>
            <span>Halo, <?= htmlspecialchars($user['name']); ?></span>
            <a class="btn btn-outline-light btn-sm" href="<?= PUBLIC_PATH ?>/logout.php">Keluar</a>
        </div>
    </div>
</header>
<main class="app-content">
    <div class="container">
    <?php if ($flashSuccess): ?>
        <div class="alert alert-success mb-4"><?= htmlspecialchars($flashSuccess); ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
        <div class="alert alert-danger mb-4"><?= htmlspecialchars($flashError); ?></div>
    <?php endif; ?>

    <div class="dashboard-grid">
        <div class="card-elevated">
            <div class="card-section">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h2 class="section-title mb-1">Katalog Produk</h2>
                        <p class="text-muted mb-0">Tambahkan ke keranjang sebelum checkout.</p>
                    </div>
                    <div class="pill pill-info">Total Produk: <?= count($products); ?></div>
                </div>
                <div class="product-grid">
                    <?php foreach ($products as $product): ?>
                        <div class="product-card">
                            <?php if (!empty($product['image_url'])): ?>
                                <img src="<?= htmlspecialchars($product['image_url']); ?>" alt="<?= htmlspecialchars($product['name']); ?>">
                            <?php endif; ?>
                            <div class="info">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <h5 class="fw-semibold mb-0"><?= htmlspecialchars($product['name']); ?></h5>
                                    <?php if (!empty($product['is_preorder'])): ?>
                                        <span class="pill pill-info" style="font-size: 0.75rem;">Preorder</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($product['description'])): ?>
                                    <p class="mb-2" style="color: rgba(255, 255, 255, 0.8) !important; font-size: 0.875rem; line-height: 1.5; min-height: 2.5rem;"><?= htmlspecialchars($product['description']); ?></p>
                                <?php else: ?>
                                    <p class="mb-2" style="color: rgba(255, 255, 255, 0.5) !important; font-size: 0.875rem; font-style: italic;">Tidak ada deskripsi</p>
                                <?php endif; ?>
                                <p class="fw-bold mb-1">Rp<?= number_format($product['price'], 0, ',', '.'); ?></p>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="pill pill-warning">Stok: <?= (int) $product['stock']; ?></span>
                                </div>
                                <form method="POST" class="row g-2 align-items-center">
                                    <input type="hidden" name="action" value="add_to_cart">
                                    <input type="hidden" name="product_id" value="<?= (int) $product['id']; ?>">
                                    <div class="col-6">
                                        <input type="number" name="quantity" min="1" max="<?= (int) $product['stock']; ?>" value="1" class="form-control form-control-sm" <?= $product['stock'] <= 0 ? 'disabled' : ''; ?>>
                                    </div>
                                    <div class="col-6">
                                        <button type="submit" class="btn btn-sm btn-gradient w-100" <?= $product['stock'] <= 0 ? 'disabled' : ''; ?>>Tambah</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$products): ?>
                        <div class="text-center text-muted py-4">Belum ada produk yang tersedia.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="card-elevated">
            <div class="card-section">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="section-title mb-0">Status Pesanan Saya</h2>
                    <div class="pill pill-info">Total: <?= count($orders); ?></div>
                </div>
                <?php if (!$orders): ?>
                    <p class="text-muted">Belum ada pesanan. Ayo mulai belanja!</p>
                <?php else: ?>
                    <div class="card-status-list list-group list-group-flush">
                        <?php foreach ($orders as $order): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <p class="mb-1 fw-semibold"><?= htmlspecialchars($order['product_name']); ?></p>
                                        <small class="text-muted d-block">Qty: <?= (int) $order['quantity']; ?> • <?= date('d M Y', strtotime($order['created_at'])); ?></small>
                                        <small class="text-muted">Bayar: <?= htmlspecialchars($order['payment_method']); ?></small>
                                    </div>
                                    <div class="text-end" style="min-width: 140px;">
                                        <span class="pill <?= $order['status'] === 'selesai' ? 'pill-success' : ($order['status'] === 'dikirim' ? 'pill-info' : ($order['status'] === 'dibatalkan' ? 'pill-danger' : 'pill-warning')); ?>">
                                            <?= $statusLabels[$order['status']] ?? ucfirst($order['status']); ?>
                                        </span>
                                        <?php if (in_array($order['status'], ['pending', 'menunggu', 'diproses'], true)): ?>
                                            <form method="POST" class="mt-2">
                                                <input type="hidden" name="action" value="cancel_order">
                                                <input type="hidden" name="order_id" value="<?= (int) $order['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger w-100">Batalkan</button>
                                            </form>
                                        <?php endif; ?>
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
</main>
</body>
</html>

