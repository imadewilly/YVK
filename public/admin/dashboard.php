<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
requireAuth(['admin']);

$user = $_SESSION['user'];
$activeTab = 'dashboard';
$pdo = getDatabaseConnection();

$statusOptions = [
    'menunggu'   => 'Menunggu',
    'pending'    => 'Menunggu', // Backward compatibility
    'diproses'   => 'Diproses',
    'preorder'   => 'Preorder',
    'dikirim'    => 'Dikirim',
    'selesai'    => 'Selesai',
    'dibatalkan' => 'Dibatalkan',
];

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

// Semua pesanan (untuk ringkasan umum)
$ordersStmt = $pdo->query('
    SELECT o.id, o.quantity, o.status, o.payment_method, o.payment_note, o.created_at,
           p.name AS product_name, u.name AS customer_name
    FROM orders o
    JOIN products p ON p.id = o.product_id
    JOIN users u ON u.id = o.user_id
    ORDER BY o.created_at DESC
');
$orders = $ordersStmt->fetchAll();
$recentOrders = array_slice($orders, 0, 5);

// Pesanan harian (hari ini)
$todayDate = (new DateTime('today'))->format('Y-m-d');
$dailyOrdersStmt = $pdo->prepare('
    SELECT o.id, o.quantity, o.status, o.payment_method, o.payment_note, o.created_at,
           p.name AS product_name, u.name AS customer_name
    FROM orders o
    JOIN products p ON p.id = o.product_id
    JOIN users u ON u.id = o.user_id
    WHERE DATE(o.created_at) = :today
    ORDER BY o.created_at DESC
');
$dailyOrdersStmt->execute(['today' => $todayDate]);
$dailyOrders = $dailyOrdersStmt->fetchAll();

$productsStmt = $pdo->query('SELECT id, name, price, stock, created_at FROM products ORDER BY created_at DESC LIMIT 6');
$latestProducts = $productsStmt->fetchAll();

$totalOrders = count($dailyOrders);
$totalProducts = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
$totalCustomers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'pelanggan'")->fetchColumn();
// Preorder: menghitung semua pesanan dengan status 'preorder' (tidak hanya hari ini)
$ordersPreorder = (int) $pdo->query("
    SELECT COUNT(*) FROM orders 
    WHERE status = 'preorder'
")->fetchColumn();
// Pesanan harus dikirim (semua hari): menghitung status pending/menunggu + diproses
// Status ini hanya berkurang ketika berubah menjadi: preorder, dikirim, selesai, atau dibatalkan
// Status 'menunggu' dan 'diproses' tidak mengurangi jumlah ini
$ordersToShip = (int) $pdo->query("
    SELECT COUNT(*) FROM orders 
    WHERE status IN ('pending', 'menunggu', 'diproses')
")->fetchColumn();

// Statistik penjualan bulanan (status selesai) menggunakan order_history
// Total bulan ini (angka di kartu)
$monthStart = (new DateTime('first day of this month'))->format('Y-m-d');
$monthEnd = (new DateTime('last day of this month'))->format('Y-m-d');

$salesStmt = $pdo->prepare("
    SELECT SUM(product_price * quantity) AS total_amount,
           COUNT(*) AS total_rows
    FROM order_history
    WHERE status = 'selesai'
      AND DATE(order_date) BETWEEN :start AND :end
");
$salesStmt->execute(['start' => $monthStart, 'end' => $monthEnd]);
$salesData = $salesStmt->fetch() ?: ['total_amount' => 0, 'total_rows' => 0];
$monthlySalesTotal = (float) ($salesData['total_amount'] ?? 0);
$monthlySalesCount = (int) ($salesData['total_rows'] ?? 0);

// Data 6 bulan terakhir untuk grafik
$chartStmt = $pdo->query("
    SELECT DATE_FORMAT(order_date, '%Y-%m-01') AS month_key,
           DATE_FORMAT(order_date, '%b %y') AS month_label,
           SUM(product_price * quantity) AS total_amount
    FROM order_history
    WHERE status = 'selesai'
      AND order_date >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
    GROUP BY month_key, month_label
    ORDER BY month_key ASC
");
$chartRows = $chartStmt->fetchAll() ?: [];

// Susun label dan data agar 6 bulan terakhir selalu muncul (meski nol)
$chartLabels = [];
$chartData = [];
for ($i = 5; $i >= 0; $i--) {
    $dt = new DateTime("first day of -$i month");
    $key = $dt->format('Y-m-01');
    $label = $dt->format('M y');
    $found = null;
    foreach ($chartRows as $row) {
        if ($row['month_key'] === $key) {
            $found = $row;
            break;
        }
    }
    $chartLabels[] = $label;
    $chartData[] = $found ? (float) $found['total_amount'] : 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= PUBLIC_PATH ?>/assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body class="app-shell">
<header class="app-navbar">
    <div class="container d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="brand">Panel Admin YVK</div>
            <div class="navbar-tabs">
                <a href="<?= PUBLIC_PATH ?>/admin/dashboard.php" class="<?= $activeTab === 'dashboard' ? 'active' : ''; ?>">Dashboard</a>
                <a href="<?= PUBLIC_PATH ?>/admin/products.php">Produk</a>
                <a href="<?= PUBLIC_PATH ?>/admin/orders.php">Pesanan</a>
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
        <section id="stats" class="mb-4">
            <div class="admin-stats">
                <div class="stat-card">
                    <p class="text-muted mb-1">Total Pesanan Hari Ini</p>
                    <h3 class="mb-0"><?= $totalOrders; ?></h3>
                    <small class="text-muted">Tanggal: <?= htmlspecialchars(date('d M Y', strtotime($todayDate))); ?></small>
                </div>
                <div class="stat-card">
                    <p class="text-muted mb-1">Preorder </p>
                    <h3 class="mb-0"><?= $ordersPreorder; ?></h3>
                </div>
                <div class="stat-card">
                    <p class="text-muted mb-1">Produk Aktif</p>
                    <h3 class="mb-0"><?= $totalProducts; ?></h3>
                </div>
                <div class="stat-card">
                    <p class="text-muted mb-1">Pelanggan Terdaftar</p>
                    <h3 class="mb-0"><?= $totalCustomers; ?></h3>
                </div>
                <div class="stat-card">
                    <p class="text-muted mb-1">Pesanan Harus Dikirim</p>
                    <h3 class="mb-0"><?= $ordersToShip; ?></h3>
                    <small class="text-muted">Status: Menunggu & Diproses (akan berkurang saat Preorder/Dikirim)</small>
                </div>
                <div class="stat-card">
                    <p class="text-muted mb-1">Penjualan Bulan Ini</p>
                    <h3 class="mb-0">Rp<?= number_format($monthlySalesTotal, 0, ',', '.'); ?></h3>
                    <small class="text-muted"><?= $monthlySalesCount; ?> transaksi selesai</small>
                </div>
            </div>
        </section>

        <section class="row g-4">
            <div class="col-lg-8">
                <div class="card-elevated h-100">
                    <div class="card-section">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h2 class="section-title mb-0">Produk Terbaru</h2>
                            <a class="btn btn-sm btn-gradient text-white" href="<?= PUBLIC_PATH ?>/admin/products.php">Kelola Produk</a>
                        </div>
                        <?php if (!$latestProducts): ?>
                            <p class="text-muted mb-0">Belum ada produk yang ditambahkan.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle">
                                    <thead>
                                    <tr>
                                        <th>Produk</th>
                                        <th>Harga</th>
                                        <th>Stok</th>
                                        <th>Ditambahkan</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($latestProducts as $product): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($product['name']); ?></td>
                                            <td>Rp<?= number_format($product['price'], 0, ',', '.'); ?></td>
                                            <td><?= (int) $product['stock']; ?></td>
                                            <td><?= date('d M Y', strtotime($product['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card-elevated h-100">
                    <div class="card-section">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h2 class="section-title mb-0">Pesanan Harian</h2>
                                <small class="text-muted">Tampil pesanan untuk tanggal hari ini (otomatis reset besok).</small>
                            </div>
                            <div class="d-flex flex-column flex-sm-row gap-2">
                                <a class="btn btn-sm btn-gradient text-white" href="<?= PUBLIC_PATH ?>/admin/export_orders_excel.php?date=<?= urlencode($todayDate); ?>">Excel</a>
                                <a class="btn btn-sm btn-outline-light" target="_blank" href="<?= PUBLIC_PATH ?>/admin/export_orders_print.php?date=<?= urlencode($todayDate); ?>">Cetak / PDF</a>
                            </div>
                        </div>
                        <?php if (!$dailyOrders): ?>
                            <p class="text-muted mb-0">Belum ada pesanan hari ini.</p>
                        <?php else: ?>
                            <div class="card-status-list list-group list-group-flush">
                                <?php foreach ($dailyOrders as $order): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <p class="mb-1 fw-semibold">#<?= $order['id']; ?> • <?= htmlspecialchars($order['customer_name']); ?></p>
                                                <small class="text-muted d-block"><?= htmlspecialchars($order['product_name']); ?> • Qty: <?= (int) $order['quantity']; ?></small>
                                                <small class="text-muted"><?= date('d M Y H:i', strtotime($order['created_at'])); ?></small>
                                            </div>
                                            <?php 
                                            $displayStatus = $order['status'];
                                            if ($displayStatus === 'pending') {
                                                $displayStatus = 'menunggu';
                                            }
                                            ?>
                                            <span class="pill <?= $displayStatus === 'selesai' ? 'pill-success' : ($displayStatus === 'dikirim' ? 'pill-info' : ($displayStatus === 'dibatalkan' ? 'pill-danger' : 'pill-warning')); ?>">
                                                <?= $statusOptions[$displayStatus] ?? ucfirst($displayStatus); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="mt-4">
            <div class="card-elevated">
                <div class="card-section">
                    <h2 class="section-title mb-3">Statistik Penjualan 6 Bulan Terakhir</h2>
                    <div class="text-muted small mb-2">Hanya menghitung transaksi dengan status selesai.</div>
                    <canvas id="salesChart" height="120"></canvas>
                </div>
            </div>
        </section>
    </div>
</main>
<script>
    (function() {
        const ctx = document.getElementById('salesChart');
        if (!ctx) return;

        const labels = <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE); ?>;
        const data = <?= json_encode($chartData, JSON_UNESCAPED_UNICODE); ?>;

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Total Penjualan (Rp)',
                    data: data,
                    fill: true,
                    tension: 0.4,
                    borderColor: 'rgba(13, 110, 253, 1)',
                    backgroundColor: 'rgba(13, 110, 253, 0.18)',
                    pointRadius: 4,
                    pointBackgroundColor: '#0d6efd'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const value = context.parsed.y || 0;
                                return 'Rp' + value.toLocaleString('id-ID');
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Rp' + value.toLocaleString('id-ID');
                            }
                        }
                    }
                }
            }
        });
    })();
</script>
</body>
</html>

