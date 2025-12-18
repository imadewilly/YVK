<?php
require_once __DIR__ . '/../includes/auth.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (attemptLogin($email, $password)) {
        $role = $_SESSION['user']['role'];
        if ($role === 'admin') {
            header('Location: ' . PUBLIC_PATH . '/admin/dashboard.php');
        } else {
            header('Location: ' . PUBLIC_PATH . '/customer/dashboard.php');
        }
        exit;
    }

    $error = 'Email atau kata sandi salah.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk | YVK Store</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= PUBLIC_PATH ?>/assets/css/style.css">
</head>
<body class="login-body">
<div class="container d-flex align-items-center justify-content-center min-vh-100">
    <div class="card shadow-lg login-card p-4 p-lg-5">
        <div class="text-center mb-4">
            <h1 class="h3 fw-bold mb-1">YVK Store</h1>
            <p class="text-muted mb-0">Masuk untuk mulai menggunakan aplikasi</p>
        </div>
        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
        <form method="POST" novalidate class="login-form">
            <div class="mb-3">
                <label for="email" class="form-label fw-semibold">Email</label>
                <input type="email" class="form-control form-control-lg" id="email" name="email" placeholder="Masukkan email Anda" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label fw-semibold">Kata Sandi</label>
                <input type="password" class="form-control form-control-lg" id="password" name="password" placeholder="Masukkan kata sandi" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-3 mb-2">Masuk</button>
        </form>
        <div class="mt-3 text-center">
            <a href="<?= PUBLIC_PATH ?>/register.php" class="btn btn-primary w-100 py-3 mb-2">Daftar pelanggan baru</a>
        </div>
    </div>
</div>
</body>
</html>

