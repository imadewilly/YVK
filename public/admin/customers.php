<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
requireAuth(['admin']);

$user = $_SESSION['user'];
$activeTab = 'customers';
$pdo = getDatabaseConnection();

$successMessages = [];
$errorMessages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_customer') {
    $userId = (int) ($_POST['user_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'pelanggan';
    $fullName = trim($_POST['full_name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $origin = trim($_POST['origin'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';

    if ($userId <= 0 || $name === '' || $email === '') {
        $errorMessages[] = 'Data utama pengguna tidak lengkap.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('UPDATE users SET name = :name, email = :email, role = :role WHERE id = :id');
            $stmt->execute([
                'name'  => $name,
                'email' => $email,
                'role'  => $role,
                'id'    => $userId,
            ]);

            if ($newPassword !== '') {
                $stmt = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
                $stmt->execute([
                    'hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                    'id'   => $userId,
                ]);
            }

            if ($fullName !== '' || $address !== '' || $phone !== '' || $origin !== '') {
                $profileExistsStmt = $pdo->prepare('SELECT id FROM customer_profiles WHERE user_id = :user_id LIMIT 1');
                $profileExistsStmt->execute(['user_id' => $userId]);
                $profile = $profileExistsStmt->fetch();

                if ($profile) {
                    $stmt = $pdo->prepare('
                        UPDATE customer_profiles
                        SET full_name = :full_name, address = :address, phone = :phone, origin = :origin
                        WHERE user_id = :user_id
                    ');
                } else {
                    $stmt = $pdo->prepare('
                        INSERT INTO customer_profiles (user_id, full_name, address, phone, origin)
                        VALUES (:user_id, :full_name, :address, :phone, :origin)
                    ');
                }

                $stmt->execute([
                    'user_id'   => $userId,
                    'full_name' => $fullName ?: $name,
                    'address'   => $address,
                    'phone'     => $phone,
                    'origin'    => $origin,
                ]);
            }

            $pdo->commit();
            $successMessages[] = 'Data customer berhasil diperbarui.';
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errorMessages[] = 'Terjadi kesalahan saat menyimpan data customer.';
            error_log('Update customer error: ' . $e->getMessage());
        }
    }
}

$customersStmt = $pdo->query('
    SELECT u.id, u.name, u.email, u.role, u.created_at,
           cp.full_name, cp.address, cp.phone, cp.origin
    FROM users u
    LEFT JOIN customer_profiles cp ON cp.user_id = u.id
    ORDER BY u.created_at DESC
');
$customers = $customersStmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Customer</title>
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
                <a href="<?= PUBLIC_PATH ?>/admin/customers.php" class="<?= $activeTab === 'customers' ? 'active' : ''; ?>">Customer</a>
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
                    <h2 class="section-title mb-0">Data Customer & User</h2>
                    <span class="pill pill-info">Total: <?= count($customers); ?></span>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle table-sm">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Profil Customer</th>
                            <th>Ubah Data</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($customers as $c): ?>
                            <tr>
                                <td>#<?= (int)$c['id']; ?></td>
                                <td><?= htmlspecialchars($c['name']); ?></td>
                                <td><?= htmlspecialchars($c['email']); ?></td>
                                <td><?= htmlspecialchars($c['role']); ?></td>
                                <td>
                                    <div class="small text-muted">
                                        <div><?= htmlspecialchars($c['full_name'] ?? '-'); ?></div>
                                        <div><?= htmlspecialchars($c['phone'] ?? '-'); ?></div>
                                        <div><?= htmlspecialchars($c['origin'] ?? '-'); ?></div>
                                    </div>
                                </td>
                                <td>
                                    <form method="POST" class="row g-2 admin-form">
                                        <input type="hidden" name="action" value="update_customer">
                                        <input type="hidden" name="user_id" value="<?= (int)$c['id']; ?>">
                                        <div class="col-12 col-md-6">
                                            <label class="form-label">Nama</label>
                                            <input type="text" class="form-control form-control-sm" name="name" value="<?= htmlspecialchars($c['name']); ?>" required>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <label class="form-label">Email</label>
                                            <input type="email" class="form-control form-control-sm" name="email" value="<?= htmlspecialchars($c['email']); ?>" required>
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <label class="form-label">Role</label>
                                            <select name="role" class="form-select form-select-sm">
                                                <option value="admin" <?= $c['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                <option value="pelanggan" <?= $c['role'] === 'pelanggan' ? 'selected' : ''; ?>>Pelanggan</option>
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <label class="form-label">No. HP</label>
                                            <input type="text" class="form-control form-control-sm" name="phone" value="<?= htmlspecialchars($c['phone'] ?? ''); ?>">
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <label class="form-label">Asal</label>
                                            <input type="text" class="form-control form-control-sm" name="origin" value="<?= htmlspecialchars($c['origin'] ?? ''); ?>">
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <label class="form-label">Nama Lengkap</label>
                                            <input type="text" class="form-control form-control-sm" name="full_name" value="<?= htmlspecialchars($c['full_name'] ?? ''); ?>">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Alamat</label>
                                            <input type="text" class="form-control form-control-sm" name="address" value="<?= htmlspecialchars($c['address'] ?? ''); ?>">
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <label class="form-label">Password Baru (opsional)</label>
                                            <input type="text" class="form-control form-control-sm" name="new_password" placeholder="Kosongkan jika tidak diubah">
                                        </div>
                                        <div class="col-12 col-md-6 d-flex align-items-end">
                                            <button type="submit" class="btn btn-sm btn-gradient text-white w-100">Simpan Perubahan</button>
                                        </div>
                                        <div class="col-12">
                                            <small class="text-muted">Password disimpan dalam bentuk terenkripsi, tidak dapat ditampilkan. Isi kolom di atas jika ingin mengganti.</small>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>


