<?php
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

if (isVerified()) {
    header("Location: index.php");
    exit();
}
?>
<?php require_once 'includes/header.php'; ?>
<div class="auth-container">
    <h2>Weryfikacja konta</h2>
    <div class="info">
        <p><strong>Twoje konto nie zostało jeszcze zweryfikowane.</strong></p>
        <p>Sprawdź swoją skrzynkę email i kliknij w link weryfikacyjny, który do Ciebie wysłaliśmy.</p>
        <p>Jeśli nie otrzymałeś emaila, możesz:</p>
        <ul>
            <li>Sprawdzić folder SPAM</li>
            <li>Poczekać kilka minut</li>
            <li>Skontaktować się z administracją</li>
        </ul>
    </div>
    <div class="auth-buttons">
        <a href="logout.php" class="btn btn-secondary">Wyloguj</a>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>