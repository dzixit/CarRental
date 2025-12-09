<?php
require_once 'includes/email_sender.php';

if (sendVerificationEmail('twoj_email@test.com', 'test_token', 'TestUser')) {
    echo "Email wysłany pomyślnie!";
} else {
    echo "Błąd wysyłania emaila!";
}
?>