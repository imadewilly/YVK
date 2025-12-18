<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$errors = [];
$success = '';
$fullName = '';
$email = '';
$address = '';
$phone = '';
$origin = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $origin = trim($_POST['origin'] ?? '');

    if ($fullName === '') {
        $errors[] = 'Nama lengkap wajib diisi.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email tidak valid.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Kata sandi minimal 8 karakter.';
    }
    if ($address === '' || $phone === '' || $origin === '') {
        $errors[] = 'Alamat, nomor HP, dan asal tempat wajib diisi.';
    }

    if (!$errors) {
        try {
            $pdo = getDatabaseConnection();
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $stmt->execute(['email' => $email]);
            if ($stmt->fetch()) {
                $errors[] = 'Email sudah terdaftar.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO users (name, email, role, password_hash) VALUES (:name, :email, :role, :password)');
                $stmt->execute([
                    'name'     => $fullName,
                    'email'    => $email,
                    'role'     => 'pelanggan',
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                ]);

                $userId = (int) $pdo->lastInsertId();
                $stmt = $pdo->prepare('INSERT INTO customer_profiles (user_id, full_name, address, phone, origin) VALUES (:user_id, :full_name, :address, :phone, :origin)');
                $stmt->execute([
                    'user_id'   => $userId,
                    'full_name' => $fullName,
                    'address'   => $address,
                    'phone'     => $phone,
                    'origin'    => $origin,
                ]);

                $pdo->commit();
                $success = 'Pendaftaran berhasil! Silakan masuk menggunakan email dan kata sandi Anda.';
            }
        } catch (PDOException $e) {
            if ($pdo && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Terjadi kesalahan saat menyimpan data.';
            error_log('Register error: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun | YVK Store</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= PUBLIC_PATH ?>/assets/css/style.css">
</head>
<body class="login-body">
<div class="container d-flex align-items-center justify-content-center min-vh-100">
    <div class="card shadow-lg register-card w-100">
        <div class="row g-0">
            <div class="col-lg-5 register-hero d-none d-lg-block">
                <div>
                    <h2 class="text-white fw-semibold mb-3">Buat Akun Pelanggan</h2>
                    <p class="text-white-50">Nikmati pengalaman belanja yang mudah dan pantau status pesanan Anda kapan saja.</p>
                </div>
                <ul class="text-white-75 small ps-3 mb-0">
                    <li>Gratis biaya pendaftaran.</li>
                    <li>Promo khusus pelanggan terdaftar.</li>
                    <li>Notifikasi status pesanan real-time.</li>
                </ul>
            </div>
            <div class="col-lg-7 p-4 p-lg-5">
                <h1 class="h3 fw-bold mb-2 text-center">Daftar YVK Store</h1>
                <p class="text-muted text-center mb-4">Isi data lengkap agar pesanan cepat diproses.</p>
                <?php if ($errors): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?= htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                <form method="POST" class="row g-3">
                    <div class="col-12">
                        <label for="full_name" class="form-label">Nama Lengkap</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required value="<?= htmlspecialchars($fullName ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required value="<?= htmlspecialchars($email ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="password" class="form-label">Kata Sandi</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <div class="form-text">Minimal 8 karakter.</div>
                    </div>
                    <div class="col-12">
                        <label for="address" class="form-label">Alamat Lengkap</label>
                        <textarea class="form-control" id="address" name="address" rows="3" required><?= htmlspecialchars($address ?? ''); ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label for="phone" class="form-label">Nomor HP</label>
                        <input type="text" class="form-control" id="phone" name="phone" required value="<?= htmlspecialchars($phone ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="origin" class="form-label">Asal Tempat</label>
                        <input type="text" class="form-control" id="origin" name="origin" required value="<?= htmlspecialchars($origin ?? ''); ?>">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary w-100 py-2">Daftar Sekarang</button>
                    </div>
                    <div class="col-12 text-center">
                        <a href="<?= PUBLIC_PATH ?>/login.php" class="text-decoration-none">Sudah punya akun? Masuk</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>

