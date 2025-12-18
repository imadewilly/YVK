<?php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

session_start();

/**
 * Attempt to authenticate the user with email/password combo.
 */
function attemptLogin(string $email, string $password): bool
{
    try {
        $pdo = getDatabaseConnection();

        $stmt = $pdo->prepare('SELECT id, name, role, password_hash FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        $_SESSION['user'] = [
            'id'   => $user['id'],
            'name' => $user['name'],
            'role' => $user['role'],
        ];

        return true;
    } catch (PDOException $e) {
        error_log('Login error: ' . $e->getMessage());
        return false;
    }
}

function requireAuth(array $roles = []): void
{
    if (!isset($_SESSION['user'])) {
        header('Location: ' . PUBLIC_PATH . '/login.php');
        exit;
    }

    if ($roles && !in_array($_SESSION['user']['role'], $roles, true)) {
        header('Location: ' . PUBLIC_PATH . '/login.php?denied=1');
        exit;
    }
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: ' . PUBLIC_PATH . '/login.php');
    exit;
}

