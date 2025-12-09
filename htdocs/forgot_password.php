<?php
require_once __DIR__ . '/config/database.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    
    $email = $_POST['email'];
    
    $query = "SELECT user_id, username, email FROM Users WHERE email = ? AND is_active = 1 AND is_verified = 1";
    $stmt = $db->prepare($query);
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // Generuj token resetowania hasła
        $reset_token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Zapisz token w bazie
        $update_query = "UPDATE Users SET verification_token = ? WHERE user_id = ?";
        $update_stmt = $db->prepare($update_query);
        
        if ($update_stmt->execute([$reset_token, $user['user_id']])) {
            // Wyślij email resetujący
            require_once __DIR__ . '/includes/email_sender.php';
            if (sendPasswordResetEmail($email, $reset_token, $user['username'])) {
                $success = "Link do resetowania hasła został wysłany na Twój email.";
            } else {
                $error = "Błąd podczas wysyłania emaila. Spróbuj ponownie.";
            }
        } else {
            $error = "Błąd podczas przetwarzania żądania. Spróbuj ponownie.";
        }
    } else {
        $error = "Nie znaleziono aktywnego konta z tym adresem email.";
    }
}
?>
<?php require_once 'includes/header.php'; ?>
<div class="auth-container">
    <h2>Resetowanie hasła</h2>
    <?php if (isset($success)): ?>
        <div class="success"><?php echo $success; ?></div>
        <p><a href="login.php" class="btn">Powrót do logowania</a></p>
    <?php else: ?>
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST" class="auth-form">
            <div class="form-group">
                <label>Email:</label>
                <input type="email" name="email" required>
            </div>
            <button type="submit" class="btn">Wyślij link resetujący</button>
        </form>
        <p><a href="login.php">Powrót do logowania</a></p>
    <?php endif; ?>
</div>
<?php require_once 'includes/footer.php'; ?>