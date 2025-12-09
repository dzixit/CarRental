<?php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
}

function isCustomer() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'customer';
}

function isVerified() {
    if (!isLoggedIn()) return false;
    
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT is_verified FROM Users WHERE user_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $user && $user['is_verified'] == 1;
}

function redirectIfNotLoggedIn() {
    if (!isLoggedIn()) {
        header("Location: ../login.php");
        exit();
    }
}

function redirectIfNotAdmin() {
    if (!isAdmin()) {
        header("Location: ../index.php");
        exit();
    }
}

function redirectIfNotCustomer() {
    if (!isCustomer()) {
        header("Location: ../index.php");
        exit();
    }
}

function redirectIfNotVerified() {
    if (isLoggedIn() && !isVerified()) {
        header("Location: ../verify_pending.php");
        exit();
    }
}
?>