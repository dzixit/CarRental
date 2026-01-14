<?php
// Ładujemy autoryzację na samym początku dla bezpiecznej sesji
require_once 'includes/auth.php';
require_once __DIR__ . '/config/database.php';

// Lista kodów krajów (dla telefonu)
$country_codes = [
    '+48' => 'Polska (+48)',
    '+49' => 'Niemcy (+49)',
    '+44' => 'Wielka Brytania (+44)',
    '+33' => 'Francja (+33)',
    '+39' => 'Włochy (+39)',
    '+34' => 'Hiszpania (+34)',
    '+420' => 'Czechy (+420)',
    '+421' => 'Słowacja (+421)',
    '+36' => 'Węgry (+36)',
    '+40' => 'Rumunia (+40)',
    '+380' => 'Ukraina (+380)',
    '+375' => 'Białoruś (+375)',
    '+370' => 'Litwa (+370)',
    '+371' => 'Łotwa (+371)',
    '+372' => 'Estonia (+372)',
    '+7' => 'Rosja (+7)',
    '+1' => 'USA/Kanada (+1)'
];

// Lista krajów pochodzenia
$countries = [
    'Poland' => 'Polska',
    'Germany' => 'Niemcy',
    'United Kingdom' => 'Wielka Brytania',
    'France' => 'Francja',
    'Italy' => 'Włochy',
    'Spain' => 'Hiszpania',
    'Czech Republic' => 'Czechy',
    'Slovakia' => 'Słowacja',
    'Hungary' => 'Węgry',
    'Romania' => 'Rumunia',
    'Ukraine' => 'Ukraina',
    'Belarus' => 'Białoruś',
    'Lithuania' => 'Litwa',
    'Latvia' => 'Łotwa',
    'Estonia' => 'Estonia',
    'Russia' => 'Rosja',
    'United States' => 'Stany Zjednoczone',
    'Canada' => 'Kanada'
];

// Lista języków
$languages = [
    'pl' => 'Polski',
    'en' => 'Angielski',
    'de' => 'Niemiecki',
    'fr' => 'Francuski',
    'it' => 'Włoski',
    'es' => 'Hiszpański'
];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    
    // 1. Weryfikacja CSRF
    if (!isset($_POST['csrf_token'])) {
        $error = "Błąd CSRF: Brak tokena.";
    } else {
        verifyCsrfToken($_POST['csrf_token']);
        
        // 2. Weryfikacja Captcha
        if (empty($_POST['captcha']) || $_SESSION['captcha_code'] !== $_POST['captcha']) {
            $error = "Nieprawidłowy kod Captcha!";
        } else {
            // Po weryfikacji usuwamy stary kod
            unset($_SESSION['captcha_code']);
            
            $username = $_POST['username'];
            $email = $_POST['email'];
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];
            $first_name = $_POST['first_name'];
            $last_name = $_POST['last_name'];
            $country_code = $_POST['country_code'];
            $phone_number = $_POST['phone_number'];
            $driver_license = $_POST['driver_license'];
            $country = $_POST['country'];
            $language = $_POST['language'];
            
            // Połącz kod kraju z numerem telefonu
            $phone = $country_code . $phone_number;
            
            // Zachowaj wprowadzone dane w formularzu w przypadku błędu
            $form_data = [
                'username' => $username,
                'email' => $email,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'country_code' => $country_code,
                'phone_number' => $phone_number,
                'driver_license' => $driver_license,
                'country' => $country,
                'language' => $language
            ];
            
            // Walidacja numeru telefonu
            $phone_regex = '/^[0-9]{7,15}$/';
            $driver_license_regex = '/^[a-zA-Z0-9\s\-\.]{5,25}$/';
            
            // 3. Walidacja siły hasła
            // Min. 8 znaków, 1 duża litera, 1 mała litera, 1 cyfra, 1 znak specjalny
            $password_regex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/';

            if ($password !== $confirm_password) {
                $error = "Hasła nie są identyczne!";
            } elseif (!preg_match($password_regex, $password)) {
                $error = "Hasło musi mieć min. 8 znaków i zawierać: małą literę, dużą literę, cyfrę oraz znak specjalny!";
            } elseif (!preg_match($phone_regex, $phone_number)) {
                $error = "Numer telefonu musi zawierać od 7 do 15 cyfr (bez spacji i myślników)!";
            } elseif (!preg_match($driver_license_regex, $driver_license)) {
                $error = "Numer prawa jazdy musi zawierać od 5 do 25 znaków (litery, cyfry, spacje, myślniki, kropki)!";
            } else {
                // Sprawdzenia bazy danych...
                $check_user_query = "SELECT user_id FROM Users WHERE email = ? OR username = ?";
                $check_user_stmt = $db->prepare($check_user_query);
                $check_user_stmt->execute([$email, $username]);
                
                $check_license_query = "SELECT customer_id FROM Customer WHERE driver_license_number = ?";
                $check_license_stmt = $db->prepare($check_license_query);
                $check_license_stmt->execute([$driver_license]);
                
                if ($check_user_stmt->rowCount() > 0) {
                    $error = "Użytkownik z tym emailem lub nazwą już istnieje!";
                } elseif ($check_license_stmt->rowCount() > 0) {
                    $error = "Użytkownik z tym numerem prawa jazdy już istnieje!";
                } else {
                    try {
                        $db->beginTransaction();
                        
                        // Dodaj klienta
                        $customer_query = "INSERT INTO Customer (first_name, last_name, email, phone, registration_date, driver_license_number) 
                                           VALUES (?, ?, ?, ?, CURDATE(), ?)";
                        $customer_stmt = $db->prepare($customer_query);
                        $customer_stmt->execute([$first_name, $last_name, $email, $phone, $driver_license]);
                        $customer_id = $db->lastInsertId();
                        
                        // Generuj token weryfikacyjny
                        $verification_token = bin2hex(random_bytes(32));
                        
                        // Dodaj użytkownika
                        $user_query = "INSERT INTO Users (username, email, password_hash, user_type, customer_id, verification_token, country, language) 
                                       VALUES (?, ?, ?, 'customer', ?, ?, ?, ?)";
                        $user_stmt = $db->prepare($user_query);
                        $user_stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $customer_id, $verification_token, $country, $language]);
                        
                        $db->commit();
                        
                        // Wyślij email weryfikacyjny
                        require_once __DIR__ . '/includes/email_sender.php';
                        if (sendVerificationEmail($email, $verification_token, $username)) {
                            $_SESSION['success'] = "Rejestracja udana! Sprawdź swoją skrzynkę email, aby aktywować konto.";
                        } else {
                            $_SESSION['error'] = "Rejestracja udana, ale nie udało się wysłać emaila weryfikacyjnego. Skontaktuj się z administracją.";
                        }
                        
                        header("Location: login.php");
                        exit();
                        
                    } catch (Exception $e) {
                        $db->rollBack();
                        $error = "Błąd podczas rejestracji: " . $e->getMessage();
                    }
                }
            }
        }
    }
}
?>
<?php require_once 'includes/header.php'; ?>
<div class="auth-container">
    <h2>Rejestracja</h2>
    <?php if (isset($error)): ?>
        <div class="error"><?php echo $error; ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['success'])): ?>
        <div class="success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    <form method="POST" class="auth-form">
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

        <div class="form-group">
            <label>Nazwa użytkownika:</label>
            <input type="text" name="username" value="<?php echo isset($form_data['username']) ? htmlspecialchars($form_data['username']) : ''; ?>" required>
        </div>
        <div class="form-group">
            <label>Email:</label>
            <input type="email" name="email" value="<?php echo isset($form_data['email']) ? htmlspecialchars($form_data['email']) : ''; ?>" required>
        </div>
        <div class="form-group">
            <label>Imię:</label>
            <input type="text" name="first_name" value="<?php echo isset($form_data['first_name']) ? htmlspecialchars($form_data['first_name']) : ''; ?>" required>
        </div>
        <div class="form-group">
            <label>Nazwisko:</label>
            <input type="text" name="last_name" value="<?php echo isset($form_data['last_name']) ? htmlspecialchars($form_data['last_name']) : ''; ?>" required>
        </div>
        
        <div class="form-group">
            <label>Kraj pochodzenia:</label>
            <select name="country" required>
                <?php foreach ($countries as $code => $name): ?>
                    <option value="<?php echo $code; ?>" 
                        <?php echo (isset($form_data['country']) && $form_data['country'] == $code) ? 'selected' : ''; ?>>
                        <?php echo $name; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label>Preferowany język:</label>
            <select name="language" required>
                <?php foreach ($languages as $code => $name): ?>
                    <option value="<?php echo $code; ?>" 
                        <?php echo (isset($form_data['language']) && $form_data['language'] == $code) ? 'selected' : ''; ?>>
                        <?php echo $name; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label>Telefon:</label>
            <div class="form-row">
                <div class="form-group" style="flex: 0 0 120px;">
                    <select name="country_code" required style="width: 100%;">
                        <?php foreach ($country_codes as $code => $label): ?>
                            <option value="<?php echo $code; ?>" 
                                <?php echo (isset($form_data['country_code']) && $form_data['country_code'] == $code) ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="flex: 1;">
                    <input type="tel" name="phone_number" 
                           pattern="[0-9]{7,15}" 
                           title="Numer telefonu musi zawierać od 7 do 15 cyfr (bez spacji i myślników)"
                           value="<?php echo isset($form_data['phone_number']) ? htmlspecialchars($form_data['phone_number']) : ''; ?>" 
                           placeholder="123456789" 
                           required>
                </div>
            </div>
            <small style="color: #666; font-size: 0.8rem;">Format: cyfry bez spacji i myślników (7-15 cyfr)</small>
        </div>
        <div class="form-group">
            <label>Numer prawa jazdy:</label>
            <input type="text" name="driver_license" 
                   pattern="[a-zA-Z0-9\s\-\.]{5,25}"
                   title="Numer prawa jazdy: 5-25 znaków (litery, cyfry, spacje, myślniki, kropki)"
                   value="<?php echo isset($form_data['driver_license']) ? htmlspecialchars($form_data['driver_license']) : ''; ?>" 
                   required>
            <small style="color: #666; font-size: 0.8rem;">
                Format: 5-25 znaków (litery, cyfry, spacje, myślniki, kropki)
            </small>
        </div>
        <div class="form-group">
            <label>Hasło:</label>
            <input type="password" name="password" required>
            <small style="color: #666; font-size: 0.8rem;">Min. 8 znaków, duża i mała litera, cyfra, znak specjalny.</small>
        </div>
        <div class="form-group">
            <label>Potwierdź hasło:</label>
            <input type="password" name="confirm_password" required>
        </div>

        <div class="form-group">
            <label>Przepisz kod z obrazka:</label>
            <div style="margin-bottom: 10px;">
                <img src="captcha.php" alt="Captcha" style="vertical-align: middle; border: 1px solid #ccc;">
            </div>
            <input type="text" name="captcha" required autocomplete="off">
        </div>

        <button type="submit" class="btn">Zarejestruj</button>
    </form>
    <p>Masz już konto? <a href="login.php">Zaloguj się</a></p>
</div>
<?php require_once 'includes/footer.php'; ?>