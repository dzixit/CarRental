<?php
require_once __DIR__ . '/config/database.php';

session_start();

$database = new Database();
$db = $database->getConnection();

$token = $_GET['token'] ?? '';
$message = '';
$message_type = '';

// Sprawdź czy token jest ważny
if ($token) {
    $query = "SELECT user_id FROM Users WHERE verification_token = ? AND is_active = 1";
    $stmt = $db->prepare($query);
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $message = "Nieprawidłowy lub przestarzały token resetowania.";
        $message_type = 'error';
    }
} else {
    $message = "Brak tokenu resetowania.";
    $message_type = 'error';
}

// Obsługa formularza resetowania hasła
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $token) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($password !== $confirm_password) {
        $error = "Hasła nie są identyczne!";
    } else {
        $update_query = "UPDATE Users SET password_hash = ?, verification_token = NULL WHERE verification_token = ?";
        $update_stmt = $db->prepare($update_query);
        
        if ($update_stmt->execute([password_hash($password, PASSWORD_DEFAULT), $token])) {
            $message = "Hasło zostało pomyślnie zmienione. Możesz się teraz zalogować.";
            $message_type = 'success';
            $token = null; // Unieważnij token po użyciu
        } else {
            $error = "Błąd podczas resetowania hasła. Spróbuj ponownie.";
        }
    }
}
?>
<?php require_once 'includes/header.php'; ?>
<div class="auth-container">
    <h2>Resetowanie hasła</h2>
    
    <?php if ($message): ?>
        <?php if ($message_type == 'success'): ?>
            <div class="success"><?php echo $message; ?></div>
            <a href="login.php" class="btn">Przejdź do logowania</a>
        <?php else: ?>
            <div class="error"><?php echo $message; ?></div>
        <?php endif; ?>
    <?php elseif ($token): ?>
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" class="auth-form">
            <div class="form-group">
                <label>Nowe hasło:</label>
                <input type="password" name="password" required>
            </div>
            <div class="form-group">
                <label>Potwierdź nowe hasło:</label>
                <input type="password" name="confirm_password" required>
            </div>
            <button type="submit" class="btn">Zresetuj hasło</button>
        </form>
    <?php endif; ?>
</div>
<?php require_once 'includes/footer.php'; ?>