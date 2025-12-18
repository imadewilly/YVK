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

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Laporan Pesanan Harian</title>
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
        @media print {
            button {
                display: none;
            }
        }
    </style>
</head>
<body>
    <button onclick="window.print()">Cetak / Simpan PDF</button>
    <h1>Laporan Pesanan Harian</h1>
    <div class="subtitle">Tanggal: <?= htmlspecialchars($dateObj->format('d M Y')); ?></div>

    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Tanggal</th>
            <th>Pelanggan</th>
            <th>Produk</th>
            <th>Qty</th>
            <th>Pembayaran</th>
            <th>Status</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$orders): ?>
            <tr>
                <td colspan="7">Belum ada pesanan pada tanggal ini.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td>#<?= (int)$order['id']; ?></td>
                    <td><?= htmlspecialchars(date('d-m-Y H:i', strtotime($order['created_at']))); ?></td>
                    <td><?= htmlspecialchars($order['customer_name']); ?></td>
                    <td><?= htmlspecialchars($order['product_name']); ?></td>
                    <td><?= (int)$order['quantity']; ?></td>
                    <td><?= htmlspecialchars($order['payment_method']); ?> - <?= htmlspecialchars($order['payment_note']); ?></td>
                    <?php 
                    $displayStatus = $order['status'];
                    if ($displayStatus === 'pending') {
                        $displayStatus = 'menunggu';
                    }
                    ?>
                    <td><?= htmlspecialchars($statusLabels[$displayStatus] ?? ucfirst($displayStatus)); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</body>
</html>


