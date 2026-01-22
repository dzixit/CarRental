<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

redirectIfNotAdmin();

$database = new Database();
$db = $database->getConnection();


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // 1. Dodawanie pojazdu
    if (isset($_POST['add_vehicle'])) {
        try {
            $brand = $_POST['brand'];
            $model_name = $_POST['model'];
            $vehicle_type = $_POST['vehicle_type'];
            $fuel_type = $_POST['fuel_type'];
            
            // Sprawdzenie/Dodanie modelu
            $stmt = $db->prepare("SELECT model_id FROM VehicleModel WHERE brand = ? AND model = ?");
            $stmt->execute([$brand, $model_name]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $model_id = $existing['model_id'];
            } else {
                $stmt = $db->prepare("INSERT INTO VehicleModel (brand, model, vehicle_type, engine_capacity, fuel_type) VALUES (?, ?, ?, 0.0, ?)");
                $stmt->execute([$brand, $model_name, $vehicle_type, $fuel_type]);
                $model_id = $db->lastInsertId();
            }

            // Wstawianie pojazdu
            $sql = "INSERT INTO Vehicle (production_year, license_plate, vin, color, mileage, technical_condition, fuel_type, transmission, status, purchase_date, value, model_id, location_id) 
                    VALUES (?, ?, ?, ?, ?, 'good', ?, ?, 'available', CURDATE(), ?, ?, 1)";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $_POST['production_year'],
                $_POST['license_plate'],
                $_POST['vin'] ?: 'VIN-MISSING-' . time(),
                $_POST['color'],
                $_POST['mileage'],
                $fuel_type,
                $_POST['transmission'],
                $_POST['value'] ?: 0,
                $model_id
            ]);
            
            $_SESSION['success'] = "Pojazd dodany pomyślnie!";
        } catch (Exception $e) {
            $_SESSION['error'] = "Błąd: " . $e->getMessage();
        }
        
        // Przekierowanie po POST (zapobiega ponownemu wysłaniu przy F5)
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // 2. Zmiana statusu z Naprawy na Dostępny
    if (isset($_POST['finish_maintenance'])) {
        $stmt = $db->prepare("UPDATE Vehicle SET status = 'available' WHERE vehicle_id = ? AND status = 'maintenance'");
        if($stmt->execute([$_POST['vehicle_id']])) {
            $_SESSION['success'] = "Samochód wrócił z naprawy!";
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // 3. Ogólna zmiana statusu w oknie modalnym
    if (isset($_POST['update_status_modal'])) {
        $stmt = $db->prepare("UPDATE Vehicle SET status = ? WHERE vehicle_id = ?");
        if($stmt->execute([$_POST['status'], $_POST['vehicle_id']])) {
            $_SESSION['success'] = "Status zaktualizowany!";
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Pobieranie danych
$search = $_GET['search'] ?? '';
$sql = "SELECT v.*, vm.brand, vm.model, vm.vehicle_type 
        FROM Vehicle v 
        JOIN VehicleModel vm ON v.model_id = vm.model_id";

if (!empty($search)) {
    $sql .= " WHERE v.license_plate LIKE ?";
    $stmt = $db->prepare($sql);
    $stmt->execute(["%$search%"]);
} else {
    $stmt = $db->query($sql);
}
$vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Przechwycenie komunikatów z sesji do zmiennych lokalnych
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;

// Usunięcie komunikatów z sesji, aby nie wyświetlały się po kolejnym odświeżeniu
unset($_SESSION['success'], $_SESSION['error']);
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="dashboard">
    <h2>Zarządzanie Pojazdami</h2>

    <?php if ($success): ?>
        <p class='success' style="color: green; font-weight: bold;"><?php echo $success; ?></p>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <p class='error' style="color: red; font-weight: bold;"><?php echo $error; ?></p>
    <?php endif; ?>

    <section class="search-section">
        <form method="GET" class="search-form">
            <input type="text" name="search" placeholder="Szukaj po nr rejestracyjnym..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn">Szukaj</button>
        </form>
    </section>

    <div class="add-vehicle-form" style="background: #f4f4f4; padding: 20px; border-radius: 8px; margin: 20px 0;">
        <h3>Dodaj nowy pojazd</h3>
        <form method="POST">
            <div class="admin-form-grid">
                <input type="text" name="brand" placeholder="Marka (np. Toyota)" required>
                <input type="text" name="model" placeholder="Model (np. Corolla)" required>
                <select name="vehicle_type">
                    <option value="sedan">Sedan</option>
                    <option value="suv">SUV</option>
                    <option value="hatchback">Hatchback</option>
                    <option value="coupe">Coupe</option>
                    <option value="convertible">Convertible</option>
                    <option value="van">Van</option>
                    <option value="truck">Truck</option>
                </select>
                <input type="text" name="license_plate" placeholder="Nr rejestracyjny" required>
                <input type="number" name="production_year" placeholder="Rok produkcji" required>
                <select name="fuel_type">
                    <option value="petrol">Benzyna</option>
                    <option value="diesel">Diesel</option>
                    <option value="electric">Elektryczny</option>
                </select>
                <input type="text" name="color" placeholder="Kolor">
                <input type="number" name="mileage" placeholder="Przebieg (km)">
                <select name="transmission">
                    <option value="manual">Manualna</option>
                    <option value="automatic">Automatyczna</option>
                </select>
                <input type="text" name="vin" placeholder="Numer VIN">
                <input type="number" name="value" placeholder="Wartość pojazdu">
            </div>
            <button type="submit" name="add_vehicle" class="btn" style="margin-top: 10px;">Dodaj do bazy</button>
        </form>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Samochód</th>
                    <th>Rejestracja</th>
                    <th>Rok</th>
                    <th>Przebieg</th>
                    <th>Stan</th>
                    <th>Akcje</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vehicles as $v): ?>
                <tr>
                    <td><?php echo htmlspecialchars($v['brand'] . ' ' . $v['model']); ?></td>
                    <td><strong><?php echo htmlspecialchars($v['license_plate']); ?></strong></td>
                    <td><?php echo htmlspecialchars($v['production_year']); ?></td>
                    <td><?php echo number_format($v['mileage'], 0, '', ' '); ?> km</td>
                    <td><span class="status-badge status-<?php echo $v['status']; ?>"><?php echo $v['status']; ?></span></td>
                    <td>
                        <button class="btn btn-sm" onclick='openModal(<?php echo json_encode($v); ?>)'>Szczegóły</button>
                        
                        <?php if ($v['status'] === 'maintenance'): ?>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Zakończyć naprawę?')">
                            <input type="hidden" name="vehicle_id" value="<?php echo $v['vehicle_id']; ?>">
                            <button type="submit" name="finish_maintenance" class="btn btn-sm btn-success">Naprawiono</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

<div id="vModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h3 id="mTitle"></h3>
        <div id="mInfo" class="grid-2"></div>
        <hr>
        <form method="POST">
            <input type="hidden" name="vehicle_id" id="mId">
            <label>Zmień status:</label>
            <select name="status" id="mStatus">
                <option value="available">Dostępny</option>
                <option value="maintenance">Naprawa</option>
                <option value="rented">Wypożyczony</option>
                <option value="archived">Zarchiwizowany</option>
            </select>
            <button type="submit" name="update_status_modal" class="btn">Zapisz</button>
        </form>
    </div>
</div>

<style>
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: 15px 0; }
.status-badge { padding: 3px 8px; border-radius: 4px; font-size: 0.8em; font-weight: bold; }
.status-available { background: #d4edda; color: #155724; }
.status-maintenance { background: #fff3cd; color: #856404; }
.btn-success { background: #2ecc71; color: white; }
.modal { display: none; position: fixed; z-index: 100; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
.modal-content { background: white; margin: 10% auto; padding: 20px; width: 400px; border-radius: 10px; }
.close { float: right; cursor: pointer; font-size: 20px; }
.search-form { margin-bottom: 20px; }
.search-form input { padding: 8px; width: 300px; border: 1px solid #ddd; }
</style>

<script>
function openModal(v) {
    document.getElementById('mTitle').innerText = v.brand + " " + v.model;
    document.getElementById('mId').value = v.vehicle_id;
    document.getElementById('mStatus').value = v.status;
    document.getElementById('mInfo').innerHTML = `
        <p><strong>VIN:</strong> ${v.vin}</p>
        <p><strong>Kolor:</strong> ${v.color}</p>
        <p><strong>Paliwo:</strong> ${v.fuel_type}</p>
        <p><strong>Skrzynia:</strong> ${v.transmission}</p>
        <p><strong>Stan techn.:</strong> ${v.technical_condition}</p>
        <p><strong>Typ:</strong> ${v.vehicle_type}</p>
    `;
    document.getElementById('vModal').style.display = "block";
}
function closeModal() { document.getElementById('vModal').style.display = "none"; }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>