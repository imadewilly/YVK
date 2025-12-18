<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
requireAuth(['admin']);

$user = $_SESSION['user'];
$activeTab = 'sales';
$pdo = getDatabaseConnection();

$startDate = $_GET['start'] ?? '';
$endDate = $_GET['end'] ?? '';

// Default: hari ini
if ($startDate === '' && $endDate === '') {
    $today = (new DateTime('today'))->format('Y-m-d');
    $startDate = $today;
    $endDate = $today;
}

$params = [];
$where = ["h.status = 'selesai'"];

if ($startDate !== '') {
    $where[] = 'DATE(h.order_date) >= :start';
    $params['start'] = $startDate;
}
if ($endDate !== '') {
    $where[] = 'DATE(h.order_date) <= :end';
    $params['end'] = $endDate;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT h.*
    FROM order_history h
    $whereSql
    ORDER BY h.order_date ASC, h.id ASC
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$grandTotal = 0;
foreach ($rows as $row) {
    $grandTotal += $row['product_price'] * $row['quantity'];
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Penjualan</title>
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
                <a href="<?= PUBLIC_PATH ?>/admin/order_history.php">History</a>
                <a href="<?= PUBLIC_PATH ?>/admin/sales_report.php" class="<?= $activeTab === 'sales' ? 'active' : ''; ?>">Laporan</a>
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
                    <h2 class="section-title mb-0">Laporan Penjualan</h2>
                    <form method="GET" class="row g-2 align-items-end">
                        <div class="col-12 col-md-4">
                            <label class="form-label text-white">Dari Tanggal</label>
                            <input type="date" name="start" class="form-control form-control-sm" value="<?= htmlspecialchars($startDate); ?>">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label text-white">Sampai Tanggal</label>
                            <input type="date" name="end" class="form-control form-control-sm" value="<?= htmlspecialchars($endDate); ?>">
                        </div>
                        <div class="col-12 col-md-4 d-flex gap-2">
                            <button type="submit" class="btn btn-sm btn-gradient text-white flex-grow-1">Terapkan</button>
                            <a href="<?= PUBLIC_PATH ?>/admin/sales_report.php" class="btn btn-sm btn-outline-light">Reset</a>
                        </div>
                    </form>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="text-muted small">
                        Periode:
                        <strong>
                            <?= $startDate ? htmlspecialchars(date('d-m-Y', strtotime($startDate))) : '-'; ?>
                            s/d
                            <?= $endDate ? htmlspecialchars(date('d-m-Y', strtotime($endDate))) : '-'; ?>
                        </strong>
                    </div>
                    <div class="d-flex flex-column flex-sm-row gap-2">
                        <a class="btn btn-sm btn-gradient text-white"
                           href="<?= PUBLIC_PATH ?>/admin/sales_report_excel.php?start=<?= urlencode($startDate); ?>&end=<?= urlencode($endDate); ?>">
                            Excel
                        </a>
                        <a class="btn btn-sm btn-outline-light" target="_blank"
                           href="<?= PUBLIC_PATH ?>/admin/sales_report_print.php?start=<?= urlencode($startDate); ?>&end=<?= urlencode($endDate); ?>">
                            Cetak / PDF
                        </a>
                    </div>
                </div>

                <?php if (!$rows): ?>
                    <p class="text-muted mb-0">Belum ada penjualan dengan status selesai pada periode ini.</p>
                <?php else: ?>
                    <div class="table-responsive mt-3">
                        <table class="table table-sm align-middle">
                            <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>ID Pesanan</th>
                                <th>Pelanggan</th>
                                <th>Produk</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Harga</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($rows as $row): ?>
                                <?php $subtotal = $row['product_price'] * $row['quantity']; ?>
                                <tr>
                                    <td><?= htmlspecialchars(date('d-m-Y', strtotime($row['order_date']))); ?></td>
                                    <td>#<?= (int)$row['order_id']; ?></td>
                                    <td><?= htmlspecialchars($row['customer_name']); ?></td>
                                    <td><?= htmlspecialchars($row['product_name']); ?></td>
                                    <td class="text-end"><?= (int)$row['quantity']; ?></td>
                                    <td class="text-end">Rp<?= number_format($row['product_price'], 0, ',', '.'); ?></td>
                                    <td class="text-end">Rp<?= number_format($subtotal, 0, ',', '.'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                            <tr>
                                <th colspan="6" class="text-end">Total Penjualan</th>
                                <th class="text-end">Rp<?= number_format($grandTotal, 0, ',', '.'); ?></th>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>
</body>
</html>


