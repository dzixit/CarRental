<?php
require_once 'includes/header.php';
?>
<div class="welcome-section">
    <h2>Witaj w Wypożyczalni Samochodów</h2>
    <?php if (isLoggedIn()): ?>
        <p>Jesteś zalogowany jako: <strong><?php echo $_SESSION['username']; ?></strong></p>
        <?php if (isAdmin()): ?>
            <p>Typ konta: <span class="admin-badge">Administrator</span></p>
            <a href="admin/dashboard.php" class="btn">Przejdź do Panelu Admina</a>
        <?php else: ?>
            <p>Typ konta: <span class="customer-badge">Klient</span></p>
            <a href="customer/dashboard.php" class="btn">Przejdź do Twojego Panelu</a>
        <?php endif; ?>
    <?php else: ?>
        <p>Zaloguj się lub zarejestruj, aby wypożyczyć samochód.</p>
        <div class="auth-buttons">
            <a href="login.php" class="btn">Zaloguj się</a>
            <a href="register.php" class="btn btn-secondary">Zarejestruj się</a>
        </div>
    <?php endif; ?>
</div>
<?php
require_once 'includes/footer.php';
?>