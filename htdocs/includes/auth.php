<?php
// Funkcja bezpiecznego startu sesji z flagami ciasteczek
function secureSessionStart() {
    if (session_status() === PHP_SESSION_NONE) {
        // Ustawienia bezpiecznych ciasteczek
        $cookieParams = session_get_cookie_params();
        session_set_cookie_params([
            'lifetime' => $cookieParams['lifetime'],
            'path' => '/',
            'domain' => $cookieParams['domain'],
            'secure' => true, // Wymaga HTTPS (zmień na false tylko jeśli testujesz na localhost bez SSL)
            'httponly' => true, // Zapobiega dostępowi JavaScript do ciasteczka sesji (ochrona XSS)
            'samesite' => 'Strict' // Ochrona przed CSRF
        ]);
        session_start();
    }
}

// Wywołujemy bezpieczny start sesji, jeśli ten plik jest dołączany
secureSessionStart();

// --- ZABEZPIECZENIE CSRF ---

// Generowanie tokena CSRF
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Weryfikacja tokena CSRF
function verifyCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        die('Błąd CSRF: Nieprawidłowy token bezpieczeństwa.');
    }
}



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