<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
requireAuth(['admin']);

$user = $_SESSION['user'];
$activeTab = 'history';
$pdo = getDatabaseConnection();

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

$successMessages = [];
$errorMessages = [];

$statusLabels = [
    'menunggu'   => 'Menunggu',
    'pending'    => 'Menunggu', // Backward compatibility
    'diproses'   => 'Diproses',
    'preorder'   => 'Preorder',
    'dikirim'    => 'Dikirim',
    'selesai'    => 'Selesai',
    'dibatalkan' => 'Dibatalkan',
];

$dateFilter = $_GET['date'] ?? '';
$search = trim($_GET['q'] ?? '');

$where = [];
$params = [];

if ($dateFilter !== '') {
    $where[] = 'DATE(h.order_date) = :date';
    $params['date'] = $dateFilter;
}

if ($search !== '') {
    $where[] = '(h.customer_name LIKE :q_name OR h.product_name LIKE :q_product)';
    $params['q_name'] = '%' . $search . '%';
    $params['q_product'] = '%' . $search . '%';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT h.*
    FROM order_history h
    $whereSql
    ORDER BY h.order_date DESC, h.id DESC
");
$stmt->execute($params);
$history = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History Pesanan</title>
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
                <a href="<?= PUBLIC_PATH ?>/admin/orders.php">Pesanan</a>
                <a href="<?= PUBLIC_PATH ?>/admin/preorder.php">Preorder</a>
                <a href="<?= PUBLIC_PATH ?>/admin/customers.php">Customer</a>
                <a href="<?= PUBLIC_PATH ?>/admin/order_history.php" class="<?= $activeTab === 'history' ? 'active' : ''; ?>">History</a>
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
        <div class="card-elevated">
            <div class="card-section">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-3 gap-3">
                    <h2 class="section-title mb-0">History Pesanan</h2>
                    <form method="GET" class="row g-2 align-items-end">
                        <div class="col-12 col-md-4">
                            <label class="form-label text-white">Tanggal</label>
                            <input type="date" name="date" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFilter); ?>">
                        </div>
                        <div class="col-12 col-md-5">
                            <label class="form-label text-white">Cari (nama pelanggan / produk)</label>
                            <input type="text" name="q" class="form-control form-control-sm" placeholder="Contoh: Budi / Kopi" value="<?= htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-12 col-md-3 d-flex gap-2">
                            <button type="submit" class="btn btn-sm btn-gradient text-white flex-grow-1">Filter</button>
                            <a href="<?= PUBLIC_PATH ?>/admin/order_history.php" class="btn btn-sm btn-outline-light">Reset</a>
                        </div>
                    </form>
                </div>
                <?php if (!$history): ?>
                    <p class="text-muted mb-0">Belum ada data history sesuai filter.</p>
                <?php else: ?>
                    <div class="table-responsive mt-3">
                        <table class="table table-sm align-middle">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tanggal</th>
                                <th>Pelanggan</th>
                                <th>Produk</th>
                                <th>Qty</th>
                                <th>Harga</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($history as $row): ?>
                                <tr>
                                    <td>#<?= (int)$row['order_id']; ?></td>
                                    <td><?= htmlspecialchars(date('d-m-Y H:i', strtotime($row['order_date']))); ?></td>
                                    <td><?= htmlspecialchars($row['customer_name']); ?></td>
                                    <td><?= htmlspecialchars($row['product_name']); ?></td>
                                    <td><?= (int)$row['quantity']; ?></td>
                                    <td>Rp<?= number_format($row['product_price'], 0, ',', '.'); ?></td>
                                    <td>
                                        <?php 
                                        $displayStatus = $row['status'];
                                        if ($displayStatus === 'pending') {
                                            $displayStatus = 'menunggu';
                                        }
                                        ?>
                                        <span class="pill <?= $displayStatus === 'selesai' ? 'pill-success' : ($displayStatus === 'dikirim' ? 'pill-info' : ($displayStatus === 'dibatalkan' ? 'pill-danger' : 'pill-warning')); ?>">
                                            <?= $statusLabels[$displayStatus] ?? ucfirst($displayStatus); ?>
                                        </span>
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


