<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
requireAuth(['admin']);

$pdo = getDatabaseConnection();

$dateParam = $_GET['date'] ?? (new DateTime('today'))->format('Y-m-d');
$dateObj = DateTime::createFromFormat('Y-m-d', $dateParam) ?: new DateTime('today');
$date = $dateObj->format('Y-m-d');

$stmt = $pdo->prepare('
    SELECT o.id, o.quantity, o.status, o.payment_method, o.payment_note, o.created_at,
           p.name AS product_name, u.name AS customer_name
    FROM orders o
    JOIN products p ON p.id = o.product_id
    JOIN users u ON u.id = o.user_id
    WHERE DATE(o.created_at) = :date
    ORDER BY o.created_at ASC
');
$stmt->execute(['date' => $date]);
$orders = $stmt->fetchAll();

$statusLabels = [
    'menunggu'   => 'Menunggu',
    'pending'    => 'Menunggu', // Backward compatibility
    'diproses'   => 'Diproses',
    'preorder'   => 'Preorder',
    'dikirim'    => 'Dikirim',
    'selesai'    => 'Selesai',
    'dibatalkan' => 'Dibatalkan',
];

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="laporan_pesanan_' . $date . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

echo "<table border=\"1\">";
echo "<tr><th colspan=\"7\">Laporan Pesanan Harian - " . htmlspecialchars($dateObj->format('d M Y')) . "</th></tr>";
echo "<tr>";
echo "<th>ID</th>";
echo "<th>Tanggal</th>";
echo "<th>Pelanggan</th>";
echo "<th>Produk</th>";
echo "<th>Qty</th>";
echo "<th>Pembayaran</th>";
echo "<th>Status</th>";
echo "</tr>";

foreach ($orders as $order) {
    echo "<tr>";
    echo "<td>#".(int)$order['id']."</td>";
    echo "<td>".htmlspecialchars(date('d-m-Y H:i', strtotime($order['created_at'])))."</td>";
    echo "<td>".htmlspecialchars($order['customer_name'])."</td>";
    echo "<td>".htmlspecialchars($order['product_name'])."</td>";
    echo "<td>".(int)$order['quantity']."</td>";
    echo "<td>".htmlspecialchars($order['payment_method'])." - ".htmlspecialchars($order['payment_note'])."</td>";
    $displayStatus = $order['status'];
    if ($displayStatus === 'pending') {
        $displayStatus = 'menunggu';
    }
    echo "<td>".htmlspecialchars($statusLabels[$displayStatus] ?? ucfirst($displayStatus))."</td>";
    echo "</tr>";
}

echo "</table>";


