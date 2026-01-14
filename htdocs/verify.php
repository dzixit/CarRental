<?php
require_once __DIR__ . '/config/database.php';

session_start();

$database = new Database();
$db = $database->getConnection();

$token = $_GET['token'] ?? '';
$message = '';
$message_type = '';

if ($token) {
    // Pobieramy tylko niezbędne dane do weryfikacji
    $query = "SELECT user_id FROM Users WHERE verification_token = ? AND is_verified = 0";
    $stmt = $db->prepare($query);
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $update_query = "UPDATE Users SET is_verified = 1, verification_token = NULL WHERE user_id = ?";
        $stmt = $db->prepare($update_query);
        if ($stmt->execute([$user['user_id']])) {
            $message = "Konto zostało pomyślnie zweryfikowane! Możesz się teraz zalogować.";
            $message_type = 'success';
            
            
            
        } else {
            $message = "Błąd podczas weryfikacji konta. Spróbuj ponownie.";
            $message_type = 'error';
        }
    } else {
        $message = "Nieprawidłowy lub przestarzały token weryfikacyjny.";
        $message_type = 'error';
    }
} else {
    $message = "Brak tokenu weryfikacyjnego.";
    $message_type = 'error';
}
?>
<?php require_once 'includes/header.php'; ?>
<div class="auth-container">
    <h2>Weryfikacja konta</h2>
    
    <?php if ($message_type == 'success'): ?>
        <div class="success"><?php echo $message; ?></div>
        <p>Twoje konto jest aktywne.</p>
        <div class="auth-buttons">
            <a href="login.php" class="btn">Przejdź do logowania</a>
        </div>
    <?php else: ?>
        <div class="error"><?php echo $message; ?></div>
        <div class="auth-buttons">
            <a href="index.php" class="btn btn-secondary">Powrót do strony głównej</a>
        </div>
    <?php endif; ?>
</div>
<?php require_once 'includes/footer.php'; ?>