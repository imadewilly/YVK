<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
requireAuth(['admin']);

$pdo = getDatabaseConnection();

$startDate = $_GET['start'] ?? '';
$endDate = $_GET['end'] ?? '';

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

$periodeLabel = ($startDate ? date('d-m-Y', strtotime($startDate)) : '-') .
    ' s/d ' .
    ($endDate ? date('d-m-Y', strtotime($endDate)) : '-');

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Laporan Penjualan</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 20px;
        }
        h1 {
            font-size: 18px;
            margin-bottom: 4px;
        }
        .subtitle {
            margin-bottom: 16px;
            color: #555;
        }
        table {
            border-collapse: collapse;
            width: 100%;
        }
        th, td {
            border: 1px solid #333;
            padding: 6px 8px;
            text-align: left;
        }
        th {
            background: #f0f0f0;
        }
        tfoot th {
            background: #e0e0e0;
        }
        .text-right {
            text-align: right;
        }
        @media print {
            button {
                display: none;
            }
        }
    </style>
</head>
<body>
    <button onclick="window.print()">Cetak / Simpan PDF</button>
    <h1>Laporan Penjualan</h1>
    <div class="subtitle">Periode: <?= htmlspecialchars($periodeLabel); ?></div>

    <table>
        <thead>
        <tr>
            <th>Tanggal</th>
            <th>ID Pesanan</th>
            <th>Pelanggan</th>
            <th>Produk</th>
            <th class="text-right">Qty</th>
            <th class="text-right">Harga</th>
            <th class="text-right">Subtotal</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr>
                <td colspan="7">Belum ada penjualan dengan status selesai pada periode ini.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($rows as $row): ?>
                <?php $subtotal = $row['product_price'] * $row['quantity']; ?>
                <tr>
                    <td><?= htmlspecialchars(date('d-m-Y', strtotime($row['order_date']))); ?></td>
                    <td>#<?= (int)$row['order_id']; ?></td>
                    <td><?= htmlspecialchars($row['customer_name']); ?></td>
                    <td><?= htmlspecialchars($row['product_name']); ?></td>
                    <td class="text-right"><?= (int)$row['quantity']; ?></td>
                    <td class="text-right">Rp<?= number_format($row['product_price'], 0, ',', '.'); ?></td>
                    <td class="text-right">Rp<?= number_format($subtotal, 0, ',', '.'); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
        <tfoot>
        <tr>
            <th colspan="6" class="text-right">Total Penjualan</th>
            <th class="text-right">Rp<?= number_format($grandTotal, 0, ',', '.'); ?></th>
        </tr>
        </tfoot>
    </table>
</body>
</html>


