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

$filename = 'laporan_penjualan_' . $startDate . '_sd_' . $endDate . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

echo "<table border=\"1\">";
echo "<tr><th colspan=\"7\">Laporan Penjualan (" .
     htmlspecialchars($startDate) . " s/d " . htmlspecialchars($endDate) .
     ")</th></tr>";
echo "<tr>";
echo "<th>Tanggal</th>";
echo "<th>ID Pesanan</th>";
echo "<th>Pelanggan</th>";
echo "<th>Produk</th>";
echo "<th>Qty</th>";
echo "<th>Harga</th>";
echo "<th>Subtotal</th>";
echo "</tr>";

foreach ($rows as $row) {
    $subtotal = $row['product_price'] * $row['quantity'];
    echo "<tr>";
    echo "<td>" . htmlspecialchars(date('d-m-Y', strtotime($row['order_date']))) . "</td>";
    echo "<td>#".(int)$row['order_id']."</td>";
    echo "<td>".htmlspecialchars($row['customer_name'])."</td>";
    echo "<td>".htmlspecialchars($row['product_name'])."</td>";
    echo "<td>".(int)$row['quantity']."</td>";
    echo "<td>Rp".number_format($row['product_price'], 0, ',', '.')."</td>";
    echo "<td>Rp".number_format($subtotal, 0, ',', '.')."</td>";
    echo "</tr>";
}

echo "<tr>";
echo "<th colspan=\"6\" style=\"text-align:right;\">Total Penjualan</th>";
echo "<th>Rp".number_format($grandTotal, 0, ',', '.')."</th>";
echo "</tr>";

echo "</table>";


