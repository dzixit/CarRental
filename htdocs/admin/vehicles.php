<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
redirectIfNotAdmin();

$database = new Database();
$db = $database->getConnection();

// Dodawanie samochodu
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_vehicle'])) {
    $license_plate = $_POST['license_plate'];
    $brand = $_POST['brand'];
    $model = $_POST['model'];
    $production_year = $_POST['production_year'];
    $color = $_POST['color'];
    $mileage = $_POST['mileage'];
    
    // Najpierw sprawdź czy model istnieje
    $model_query = "SELECT model_id FROM VehicleModel WHERE brand = ? AND model = ?";
    $stmt = $db->prepare($model_query);
    $stmt->execute([$brand, $model]);
    $existing_model = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing_model) {
        $model_id = $existing_model['model_id'];
    } else {
        // Dodaj nowy model
        $insert_model = "INSERT INTO VehicleModel (brand, model, vehicle_type, fuel_type) VALUES (?, ?, 'sedan', 'petrol')";
        $stmt = $db->prepare($insert_model);
        $stmt->execute([$brand, $model]);
        $model_id = $db->lastInsertId();
    }
    
    // Dodaj pojazd
    $vehicle_query = "INSERT INTO Vehicle (license_plate, production_year, color, mileage, status, model_id, location_id) 
                     VALUES (?, ?, ?, ?, 'available', ?, 1)";
    $stmt = $db->prepare($vehicle_query);
    if ($stmt->execute([$license_plate, $production_year, $color, $mileage, $model_id])) {
        $success = "Samochód został dodany!";
    } else {
        $error = "Błąd podczas dodawania samochodu!";
    }
}

// Zmiana statusu
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $vehicle_id = $_POST['vehicle_id'];
    $status = $_POST['status'];
    
    $query = "UPDATE Vehicle SET status = ? WHERE vehicle_id = ?";
    $stmt = $db->prepare($query);
    if ($stmt->execute([$status, $vehicle_id])) {
        $success = "Status samochodu został zaktualizowany!";
    } else {
        $error = "Błąd podczas aktualizacji statusu!";
    }
}

// Pobierz samochody
$vehicles = $db->query("SELECT v.*, vm.brand, vm.model FROM Vehicle v JOIN VehicleModel vm ON v.model_id = vm.model_id")->fetchAll(PDO::FETCH_ASSOC);
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>
<div class="dashboard">
    <h2>Zarządzanie Samochodami</h2>

    <?php if (isset($success)): ?>
        <div class="success"><?php echo $success; ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="error"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="add-vehicle-form">
        <h3>Dodaj nowy samochód</h3>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Marka:</label>
                    <input type="text" name="brand" required>
                </div>
                <div class="form-group">
                    <label>Model:</label>
                    <input type="text" name="model" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Numer rejestracyjny:</label>
                    <input type="text" name="license_plate" required>
                </div>
                <div class="form-group">
                    <label>Rok produkcji:</label>
                    <input type="number" name="production_year" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Kolor:</label>
                    <input type="text" name="color" required>
                </div>
                <div class="form-group">
                    <label>Przebieg:</label>
                    <input type="number" name="mileage" required>
                </div>
            </div>
            <button type="submit" name="add_vehicle" class="btn">Dodaj samochód</button>
        </form>
    </div>

    <div class="vehicles-list">
        <h3>Lista Samochodów</h3>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Samochód</th>
                        <th>Rejestracja</th>
                        <th>Rok</th>
                        <th>Przebieg</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vehicles as $vehicle): ?>
                    <tr>
                        <td><?php echo $vehicle['brand'] . ' ' . $vehicle['model']; ?></td>
                        <td><?php echo $vehicle['license_plate']; ?></td>
                        <td><?php echo $vehicle['production_year']; ?></td>
                        <td><?php echo $vehicle['mileage']; ?> km</td>
                        <td>
                            <form method="POST" class="status-form">
                                <input type="hidden" name="vehicle_id" value="<?php echo $vehicle['vehicle_id']; ?>">
                                <select name="status" onchange="this.form.submit()">
                                    <option value="available" <?php echo $vehicle['status'] == 'available' ? 'selected' : ''; ?>>Dostępny</option>
                                    <option value="rented" <?php echo $vehicle['status'] == 'rented' ? 'selected' : ''; ?>>Wypożyczony</option>
                                    <option value="maintenance" <?php echo $vehicle['status'] == 'maintenance' ? 'selected' : ''; ?>>Naprawa</option>
                                    <option value="archived" <?php echo $vehicle['status'] == 'archived' ? 'selected' : ''; ?>>Zarchiwizowany</option>
                                </select>
                                <button type="submit" name="update_status" style="display:none">Zapisz</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>