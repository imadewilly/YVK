<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
requireAuth(['admin']);

$user = $_SESSION['user'];
$activeTab = 'orders';
$pdo = getDatabaseConnection();
$successMessages = [];
$errorMessages = [];
$statusOptions = [
    'menunggu'   => 'Menunggu',
    'pending'    => 'Menunggu', // Backward compatibility
    'diproses'   => 'Diproses',
    'preorder'   => 'Preorder',
    'dikirim'    => 'Dikirim',
    'selesai'    => 'Selesai',
    'dibatalkan' => 'Dibatalkan',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_order_status') {
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $status = $_POST['status'] ?? 'pending';
    
    // Convert 'menunggu' ke 'pending' untuk database compatibility
    $dbStatus = ($status === 'menunggu') ? 'pending' : $status;
    
    // Validasi status yang diizinkan (termasuk 'diproses' dan 'preorder' sebagai status terpisah)
    $allowedStatuses = ['pending', 'menunggu', 'diproses', 'preorder', 'dikirim', 'selesai', 'dibatalkan'];
    if (!in_array($status, $allowedStatuses)) {
        $errorMessages[] = 'Status tidak dikenal: ' . htmlspecialchars($status);
    } else {
        try {
            $stmt = $pdo->prepare('UPDATE orders SET status = :status WHERE id = :id');
            $stmt->execute(['status' => $dbStatus, 'id' => $orderId]);

            // Update juga history agar sinkron dengan status terbaru
            $historyStmt = $pdo->prepare('UPDATE order_history SET status = :status WHERE order_id = :order_id');
            $historyStmt->execute(['status' => $dbStatus, 'order_id' => $orderId]);

            $successMessages[] = 'Status pesanan #' . $orderId . ' diperbarui.';
        } catch (PDOException $e) {
            $errorMessages[] = 'Gagal memperbarui status pesanan.';
            // Jika error karena ENUM tidak memiliki nilai 'preorder', beri pesan yang jelas
            if (strpos($e->getMessage(), 'preorder') !== false || strpos($e->getMessage(), 'ENUM') !== false) {
                $errorMessages[] = 'Status "preorder" belum ditambahkan ke database. Silakan jalankan file database_migration_add_preorder.sql di phpMyAdmin.';
            }
            error_log('Update order status error: ' . $e->getMessage());
        }
    }
}

// Sinkronisasi order_history dengan orders (memastikan semua status selalu update)
// Ini akan update order_history untuk semua order yang statusnya berbeda
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

$ordersStmt = $pdo->prepare('
    SELECT o.id, o.quantity, o.status, o.payment_method, o.payment_note, o.created_at,
           p.name AS product_name, u.name AS customer_name
    FROM orders o
    JOIN products p ON p.id = o.product_id
    JOIN users u ON u.id = o.user_id
    ORDER BY o.created_at DESC
');
$ordersStmt->execute();
$orders = $ordersStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pesanan</title>
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
                <a href="<?= PUBLIC_PATH ?>/admin/products.php">Produk</a>
                <a href="<?= PUBLIC_PATH ?>/admin/orders.php" class="<?= $activeTab === 'orders' ? 'active' : ''; ?>">Pesanan</a>
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

        <div class="card-elevated">
            <div class="card-section">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="section-title mb-0">Daftar Pesanan</h2>
                    <div class="pill pill-info">Total: <?= count($orders); ?></div>
                </div>
                <?php if (!$orders): ?>
                    <p class="text-muted">Belum ada pesanan masuk.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Pelanggan</th>
                                <th>Produk</th>
                                <th>Qty</th>
                                <th>Pembayaran</th>
                                <th>Status</th>
                                <th>Tindakan</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>#<?= $order['id']; ?></td>
                                    <td><?= htmlspecialchars($order['customer_name']); ?></td>
                                    <td><?= htmlspecialchars($order['product_name']); ?></td>
                                    <td><?= (int) $order['quantity']; ?></td>
                                    <td>
                                        <div class="small">
                                            <div><?= htmlspecialchars($order['payment_method']); ?></div>
                                            <div class="text-muted"><?= htmlspecialchars($order['payment_note']); ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                        $displayStatus = $order['status'];
                                        if ($displayStatus === 'pending') {
                                            $displayStatus = 'menunggu';
                                        }
                                        ?>
                                        <span class="pill <?= $displayStatus === 'selesai' ? 'pill-success' : ($displayStatus === 'dikirim' ? 'pill-info' : ($displayStatus === 'dibatalkan' ? 'pill-danger' : 'pill-warning')); ?>">
                                            <?= $statusOptions[$displayStatus] ?? ucfirst($displayStatus); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" class="d-flex gap-2">
                                            <input type="hidden" name="action" value="update_order_status">
                                            <input type="hidden" name="order_id" value="<?= $order['id']; ?>">
                                            <select name="status" class="form-select form-select-sm admin-form">
                                                <?php 
                                                // Tampilkan semua status: Menunggu, Preorder, Dikirim, Selesai, Dibatalkan
                                                // Jika status saat ini adalah 'pending', tampilkan sebagai 'menunggu'
                                                $currentStatus = $order['status'];
                                                if ($currentStatus === 'pending') {
                                                    $currentStatus = 'menunggu';
                                                }
                                                // Tampilkan status yang diizinkan (Diproses dan Preorder terpisah)
                                                $displayStatuses = [
                                                    'menunggu' => 'Menunggu',
                                                    'diproses' => 'Diproses',
                                                    'preorder' => 'Preorder',
                                                    'dikirim' => 'Dikirim',
                                                    'selesai' => 'Selesai',
                                                    'dibatalkan' => 'Dibatalkan',
                                                ];
                                                foreach ($displayStatuses as $value => $label): 
                                                    // Pastikan status 'diproses' terdeteksi dengan benar
                                                    $isSelected = ($currentStatus === $value);
                                                ?>
                                                    <option value="<?= htmlspecialchars($value); ?>" <?= $isSelected ? 'selected' : ''; ?>>
                                                        <?= htmlspecialchars($label); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button class="btn btn-sm btn-outline-primary" type="submit">Simpan</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>
</body>
</html>

