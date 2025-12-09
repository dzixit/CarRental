<?php
require_once __DIR__ . '/config/database.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    $query = "SELECT u.*, c.customer_id, e.employee_id 
              FROM Users u 
              LEFT JOIN Customer c ON u.customer_id = c.customer_id 
              LEFT JOIN Employee e ON u.employee_id = e.employee_id 
              WHERE u.email = ? AND u.is_active = 1";
    $stmt = $db->prepare($query);
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password_hash'])) {
        if ($user['is_verified'] == 1) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['customer_id'] = $user['customer_id'];
            
            // Aktualizuj ostatnie logowanie
            $update_query = "UPDATE Users SET last_login = NOW() WHERE user_id = ?";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->execute([$user['user_id']]);
            
            header("Location: index.php");
            exit();
        } else {
            $error = "Konto nie zostało aktywowane. Sprawdź swój email i kliknij w link weryfikacyjny.";
        }
    } else {
        $error = "Nieprawidłowy email lub hasło!";
    }
}
?>
<?php require_once 'includes/header.php'; ?>
<div class="auth-container">
    <h2>Logowanie</h2>
    <?php if (isset($error)): ?>
        <div class="error"><?php echo $error; ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['success'])): ?>
        <div class="success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <form method="POST" class="auth-form">
        <div class="form-group">
            <label>Email:</label>
            <input type="email" name="email" required>
        </div>
        <div class="form-group">
            <label>Hasło:</label>
            <input type="password" name="password" required>
        </div>
        <button type="submit" class="btn">Zaloguj</button>
    </form>
    <p>Nie masz konta? <a href="register.php">Zarejestruj się</a></p>
    <p><a href="forgot_password.php">Zapomniałeś hasła?</a></p>
</div>
<?php require_once 'includes/footer.php'; ?>