<?php
require_once __DIR__ . '/auth.php';
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wypożyczalnia Samochodów</title>
    <link rel="stylesheet" href="/style.css?v=<?php echo time(); ?>">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <h1 class="nav-logo">
                <a href="/index.php" style="text-decoration: none; color: inherit;">CarRental</a>
            </h1>
            <div class="nav-menu">
                <?php if (isLoggedIn()): ?>
                    <?php if (isAdmin()): ?>
                        <a href="../admin/dashboard.php">Panel Admina</a>
                        <a href="../admin/customers.php">Klienci</a>
                        <a href="../admin/vehicles.php">Samochody</a>
                    <?php else: ?>
                        <a href="../customer/dashboard.php">Mój Panel</a>
                        <a href="../customer/reservations.php">Rezerwacje</a>
                    <?php endif; ?>
                    <a href="../logout.php" class="logout-btn">Wyloguj</a>
                <?php else: ?>
                    <a href="../login.php">Zaloguj</a>
                    <a href="../register.php">Rejestracja</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <div class="container">