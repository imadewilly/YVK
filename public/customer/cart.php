<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
requireAuth(['pelanggan', 'admin']);

$user = $_SESSION['user'];
$pdo = getDatabaseConnection();
$success = '';
$error = '';
$paymentInfo = 'Transfer ke BCA 123456789 a.n YVK Store';
$confirmPhone = '085162798225';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
if (!isset($_SESSION['cart'][$user['id']])) {
    $_SESSION['cart'][$user['id']] = [];
}
$cart = &$_SESSION['cart'][$user['id']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'remove_from_cart') {
        $productId = (int) ($_POST['product_id'] ?? 0);
        unset($cart[$productId]);
    }

    if ($action === 'clear_cart') {
        $cart = [];
    }

    if ($action === 'checkout') {
        if (!$cart) {
            $error = 'Keranjang masih kosong.';
        } else {
            try {
                $pdo->beginTransaction();
                $orderNumbers = [];

                foreach ($cart as $productId => $item) {
                    $quantity = (int) $item['quantity'];
                    $stmt = $pdo->prepare('SELECT id, name, stock, price, is_preorder FROM products WHERE id = :id FOR UPDATE');
                    $stmt->execute(['id' => $productId]);
                    $product = $stmt->fetch();

                    if (!$product) {
                        throw new RuntimeException('Produk tidak ditemukan saat checkout.');
                    }
                    if ($product['stock'] < $quantity) {
                        throw new RuntimeException('Stok produk ' . $product['name'] . ' tidak mencukupi.');
                    }

                    // Jika produk adalah Preorder, status langsung menjadi 'preorder'
                    // Jika produk biasa, status menjadi 'pending' (menunggu)
                    $orderStatus = (!empty($product['is_preorder'])) ? 'preorder' : 'pending';

                    $insert = $pdo->prepare('
                        INSERT INTO orders (user_id, product_id, quantity, status, payment_method, payment_note)
                        VALUES (:user_id, :product_id, :quantity, :status, :payment_method, :payment_note)
                    ');
                    $insert->execute([
                        'user_id'        => $user['id'],
                        'product_id'     => $productId,
                        'quantity'       => $quantity,
                        'status'         => $orderStatus,
                        'payment_method' => 'Transfer BCA',
                        'payment_note'   => $paymentInfo,
                    ]);

                    $newOrderId = (int) $pdo->lastInsertId();
                    $orderNumbers[] = '#' . $newOrderId;

                    // Simpan ke history agar tidak hilang meski produk dihapus
                    $history = $pdo->prepare('
                        INSERT INTO order_history (order_id, user_id, customer_name, product_name, product_price, quantity, status, payment_method, payment_note, order_date)
                        VALUES (:order_id, :user_id, :customer_name, :product_name, :product_price, :quantity, :status, :payment_method, :payment_note, NOW())
                    ');
                    $history->execute([
                        'order_id'       => $newOrderId,
                        'user_id'        => $user['id'],
                        'customer_name'  => $user['name'],
                        'product_name'   => $product['name'],
                        'product_price'  => $product['price'],
                        'quantity'       => $quantity,
                        'status'         => $orderStatus, // Status sesuai dengan produk (preorder atau pending)
                        'payment_method' => 'Transfer BCA',
                        'payment_note'   => $paymentInfo,
                    ]);

                    $updateStock = $pdo->prepare('UPDATE products SET stock = stock - :qty WHERE id = :id');
                    $updateStock->execute(['qty' => $quantity, 'id' => $productId]);
                }

                $pdo->commit();
                $cart = [];
                $success = 'Pesanan berhasil dibuat (' . implode(', ', $orderNumbers) . '). ' .
                    'Segera ' . $paymentInfo . ' lalu konfirmasi pembayaran ke ' . $confirmPhone . '.';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = $e instanceof RuntimeException ? $e->getMessage() : 'Terjadi kesalahan saat checkout.';
                error_log('Checkout error: ' . $e->getMessage());
            }
        }
    }
}

$cartDetails = [];
$cartTotal = 0;
if ($cart) {
    $placeholders = implode(',', array_fill(0, count($cart), '?'));
    $stmt = $pdo->prepare("SELECT id, name, price FROM products WHERE id IN ($placeholders)");
    $stmt->execute(array_keys($cart));
    foreach ($stmt->fetchAll() as $product) {
        $id = $product['id'];
        $quantity = $cart[$id]['quantity'];
        $subtotal = $product['price'] * $quantity;
        $cartDetails[] = [
            'id'       => $id,
            'name'     => $product['name'],
            'price'    => $product['price'],
            'quantity' => $quantity,
            'subtotal' => $subtotal,
        ];
        $cartTotal += $subtotal;
    }
}

$ordersStmt = $pdo->prepare('
    SELECT o.id, o.quantity, o.status, o.created_at, p.name AS product_name
    FROM orders o
    JOIN products p ON p.id = o.product_id
    WHERE o.user_id = :user_id
    ORDER BY o.created_at DESC
');
$ordersStmt->execute(['user_id' => $user['id']]);
$orders = $ordersStmt->fetchAll();
$statusLabels = [
    'menunggu'   => 'Menunggu',
    'pending'    => 'Menunggu', // Backward compatibility
    'diproses'   => 'Diproses',
    'preorder'   => 'Preorder',
    'dikirim'    => 'Dikirim',
    'selesai'    => 'Selesai',
    'dibatalkan' => 'Dibatalkan',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang Saya</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="<?= PUBLIC_PATH ?>/customer/dashboard.php">YVK Store</a>
        <div class="d-flex align-items-center gap-2">
            <a href="<?= PUBLIC_PATH ?>/customer/dashboard.php" class="btn btn-outline-light btn-sm">Belanja Lagi</a>
            <a class="btn btn-outline-light btn-sm" href="<?= PUBLIC_PATH ?>/logout.php">Keluar</a>
        </div>
    </div>
</nav>
<main class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-1">Keranjang</h1>
            <p class="text-muted mb-0">Review barang sebelum checkout.</p>
        </div>
        <div>
            <span class="text-muted">Metode pembayaran: <strong>Transfer BCA</strong></span>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 mb-0">Daftar Barang</h2>
                <?php if ($cartDetails): ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="clear_cart">
                        <button type="submit" class="btn btn-sm btn-outline-danger">Kosongkan Keranjang</button>
                    </form>
                <?php endif; ?>
            </div>
            <?php if (!$cartDetails): ?>
                <p class="text-muted mb-0">Keranjang masih kosong. Mulai belanja di dashboard pelanggan.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                        <tr>
                            <th>Produk</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Subtotal</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($cartDetails as $item): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($item['name']); ?></strong><br>
                                    <small class="text-muted">Rp<?= number_format($item['price'], 0, ',', '.'); ?>/pcs</small>
                                </td>
                                <td class="text-end"><?= (int) $item['quantity']; ?></td>
                                <td class="text-end">Rp<?= number_format($item['subtotal'], 0, ',', '.'); ?></td>
                                <td class="text-end">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="remove_from_cart">
                                        <input type="hidden" name="product_id" value="<?= (int) $item['id']; ?>">
                                        <button type="submit" class="btn btn-link text-danger p-0 small">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                        <tr>
                            <th colspan="2">Total Bayar</th>
                            <th class="text-end">Rp<?= number_format($cartTotal, 0, ',', '.'); ?></th>
                            <th></th>
                        </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="alert alert-info">
                    Setelah checkout, transfer ke <strong><?= htmlspecialchars($paymentInfo); ?></strong> lalu kirim bukti pembayaran ke
                    <strong><?= $confirmPhone; ?></strong> (SMS/WhatsApp) agar pesanan diproses.
                </div>
                <form method="POST" class="d-flex justify-content-end">
                    <input type="hidden" name="action" value="checkout">
                    <button type="submit" class="btn btn-primary btn-lg">Checkout Sekarang</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <h2 class="h5 mb-3">Riwayat Pesanan</h2>
            <?php if (!$orders): ?>
                <p class="text-muted">Belum ada pesanan.</p>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($orders as $order): ?>
                        <div class="list-group-item px-0">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <p class="mb-1 fw-semibold"><?= htmlspecialchars($order['product_name']); ?></p>
                                    <small class="text-muted">Qty: <?= (int) $order['quantity']; ?> • <?= date('d M Y', strtotime($order['created_at'])); ?></small>
                                </div>
                                <span class="badge text-bg-<?= $order['status'] === 'selesai' ? 'success' : ($order['status'] === 'dikirim' ? 'info' : 'warning'); ?>">
                                    <?= $statusLabels[$order['status']] ?? ucfirst($order['status']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>
</body>
</html>

