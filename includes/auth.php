<?php
require_once __DIR__ . '/db.php';
session_start();

function register($name, $email, $password) {
    global $pdo;
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)");
    return $stmt->execute([$name, $email, $hash]);
}

function login($email, $password) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['last_activity'] = time();
        return true;
    }
    return false;
}

function isLoggedIn() {
    if (isset($_SESSION['user_id']) && (time() - $_SESSION['last_activity'] < SESSION_TIMEOUT)) {
        $_SESSION['last_activity'] = time();
        return true;
    }
    return false;
}

function logout() {
    session_destroy();
    header("Location: " . APP_URL . "/pages/login.php");
    exit;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: " . APP_URL . "/pages/login.php");
        exit;
    }
}
?>