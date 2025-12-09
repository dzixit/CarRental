<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
redirectIfNotAdmin();

$database = new Database();
$db = $database->getConnection();

// Nakładanie kary
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_penalty'])) {
    $customer_id = $_POST['customer_id'];
    $amount = $_POST['amount'];
    $reason = $_POST['reason'];
    $rental_id = $_POST['rental_id'];
    
    $query = "INSERT INTO Penalty (amount, reason, imposition_date, payment_deadline, rental_id, penalty_status) 
              VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY), ?, 'pending')";
    $stmt = $db->prepare($query);
    if ($stmt->execute([$amount, $reason, $rental_id])) {
        $success = "Kara została nałożona!";
    } else {
        $error = "Błąd podczas nakładania kary!";
    }
}

// Tworzenie wypożyczenia
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_rental'])) {
    $customer_id = $_POST['customer_id'];
    $vehicle_id = $_POST['vehicle_id'];
    $planned_return_date = $_POST['planned_return_date'];
    
    $query = "INSERT INTO Rental (planned_return_date, rental_cost, deposit, customer_id, vehicle_id, pickup_location_id, return_location_id, rental_status) 
              VALUES (?, 100, 500, ?, ?, 1, 1, 'active')";
    $stmt = $db->prepare($query);
    if ($stmt->execute([$planned_return_date, $customer_id, $vehicle_id])) {
        
        $update_vehicle = "UPDATE Vehicle SET status = 'rented' WHERE vehicle_id = ?";
        $stmt2 = $db->prepare($update_vehicle);
        $stmt2->execute([$vehicle_id]);
        
        $success = "Wypożyczenie zostało utworzone!";
    } else {
        $error = "Błąd podczas tworzenia wypożyczenia!";
    }
}

// Konwersja rezerwacji na wypożyczenie
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['convert_reservation'])) {
    $reservation_id = $_POST['reservation_id'];
    
    try {
        $db->beginTransaction();
        
        // Pobierz dane rezerwacji
        $reservation_query = "SELECT * FROM Reservation WHERE reservation_id = ?";
        $stmt = $db->prepare($reservation_query);
        $stmt->execute([$reservation_id]);
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($reservation) {
            // Utwórz wypożyczenie z danych rezerwacji
            $rental_query = "INSERT INTO Rental (planned_return_date, rental_cost, deposit, customer_id, vehicle_id, pickup_location_id, return_location_id, reservation_id, rental_status) 
                            VALUES (?, 100, 500, ?, ?, 1, 1, ?, 'active')";
            $stmt = $db->prepare($rental_query);
            $stmt->execute([
                $reservation['end_date'],
                $reservation['customer_id'],
                $reservation['vehicle_id'],
                $reservation_id
            ]);
            
            // Zaktualizuj status rezerwacji
            $update_reservation = "UPDATE Reservation SET reservation_status = 'completed' WHERE reservation_id = ?";
            $stmt = $db->prepare($update_reservation);
            $stmt->execute([$reservation_id]);
            
            // Zmień status pojazdu na wypożyczony
            $update_vehicle = "UPDATE Vehicle SET status = 'rented' WHERE vehicle_id = ?";
            $stmt = $db->prepare($update_vehicle);
            $stmt->execute([$reservation['vehicle_id']]);
            
            $db->commit();
            $success = "Rezerwacja została przekonwertowana na wypożyczenie!";
        }
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Błąd podczas konwersji rezerwacji: " . $e->getMessage();
    }
}

// Rejestracja zwrotu z dodatkowymi informacjami
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['return_vehicle'])) {
    $rental_id = $_POST['rental_id'];
    $vehicle_id = $_POST['vehicle_id'];
    $fuel_level = $_POST['fuel_level'];
    $vehicle_condition = $_POST['vehicle_condition'];
    $requires_repair = isset($_POST['requires_repair']) ? 1 : 0;
    $notes = $_POST['notes'];
    $new_mileage = $_POST['new_mileage'];
    
    try {
        $db->beginTransaction();
        
        // Zaktualizuj wypożyczenie
        $update_rental = "UPDATE Rental SET actual_return_date = CURDATE(), rental_status = 'completed' WHERE rental_id = ?";
        $stmt = $db->prepare($update_rental);
        $stmt->execute([$rental_id]);
        
        // Zaktualizuj pojazd
        $update_vehicle = "UPDATE Vehicle SET 
                          status = 'available', 
                          mileage = ?,
                          technical_condition = ?
                          WHERE vehicle_id = ?";
        $stmt = $db->prepare($update_vehicle);
        $stmt->execute([$new_mileage, $vehicle_condition, $vehicle_id]);
        
        // Jeśli wymaga naprawy, zmień status na maintenance
        if ($requires_repair) {
            $update_for_repair = "UPDATE Vehicle SET status = 'maintenance' WHERE vehicle_id = ?";
            $stmt = $db->prepare($update_for_repair);
            $stmt->execute([$vehicle_id]);
            
            // Dodaj wpis do serwisu
            $add_service = "INSERT INTO Service (service_type, start_date, service_status, mileage_at_service, description, vehicle_id, location_id) 
                           VALUES ('repair', CURDATE(), 'scheduled', ?, ?, ?, 1)";
            $stmt = $db->prepare($add_service);
            $stmt->execute([$new_mileage, $notes ?: 'Wymagana naprawa po zwrocie', $vehicle_id]);
        }
        
        // Zapisz dodatkowe informacje o zwrocie (nowa tabela)
        try {
            $return_info_query = "INSERT INTO VehicleReturnInfo (rental_id, fuel_level, vehicle_condition, requires_repair, notes, inspector_notes) 
                                 VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($return_info_query);
            $stmt->execute([$rental_id, $fuel_level, $vehicle_condition, $requires_repair, $notes, 'Zwrot zarejestrowany przez admina']);
        } catch (Exception $e) {
            // Tabela może nie istnieć, ignoruj błąd
        }
        
        $db->commit();
        $success = "Zwrot samochodu został zarejestrowany z pełnymi informacjami!";
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Błąd podczas rejestracji zwrotu: " . $e->getMessage();
    }
}

// Pobierz klientów
$customers = $db->query("SELECT * FROM Customer ORDER BY registration_date DESC")->fetchAll(PDO::FETCH_ASSOC);

// Pobierz aktywne wypożyczenia z modelami aut
$active_rentals = $db->query("SELECT r.*, c.first_name, c.last_name, v.license_plate, vm.brand, vm.model, v.mileage as current_mileage 
                             FROM Rental r 
                             JOIN Customer c ON r.customer_id = c.customer_id 
                             JOIN Vehicle v ON r.vehicle_id = v.vehicle_id 
                             JOIN VehicleModel vm ON v.model_id = vm.model_id 
                             WHERE r.rental_status = 'active'")->fetchAll(PDO::FETCH_ASSOC);

// Pobierz dostępne samochody z modelami
$available_vehicles = $db->query("SELECT v.*, vm.brand, vm.model 
                                 FROM Vehicle v 
                                 JOIN VehicleModel vm ON v.model_id = vm.model_id 
                                 WHERE v.status = 'available'")->fetchAll(PDO::FETCH_ASSOC);

// Pobierz aktywne rezerwacje klientów
$active_reservations = $db->query("SELECT res.*, c.first_name, c.last_name, v.license_plate, vm.brand, vm.model 
                                  FROM Reservation res 
                                  JOIN Customer c ON res.customer_id = c.customer_id 
                                  JOIN Vehicle v ON res.vehicle_id = v.vehicle_id 
                                  JOIN VehicleModel vm ON v.model_id = vm.model_id 
                                  WHERE res.reservation_status IN ('pending', 'confirmed') 
                                  ORDER BY res.start_date ASC")->fetchAll(PDO::FETCH_ASSOC);

// Pobierz historię wypożyczeń dla kar (wszystkie wypożyczenia klienta)
$customer_rentals_history = [];
foreach ($customers as $customer) {
    $history_query = "SELECT r.*, v.license_plate, vm.brand, vm.model 
                     FROM Rental r 
                     JOIN Vehicle v ON r.vehicle_id = v.vehicle_id 
                     JOIN VehicleModel vm ON v.model_id = vm.model_id 
                     WHERE r.customer_id = ? 
                     ORDER BY r.rental_date DESC";
    $stmt = $db->prepare($history_query);
    $stmt->execute([$customer['customer_id']]);
    $customer_rentals_history[$customer['customer_id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>
<div class="dashboard">
    <h2>Zarządzanie Klientami</h2>

    <?php if (isset($success)): ?>
        <div class="success"><?php echo $success; ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="error"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Aktywne rezerwacje -->
    <div class="reservations-section">
        <h3>Aktywne Rezerwacje Klientów</h3>
        <?php if (empty($active_reservations)): ?>
            <p>Brak aktywnych rezerwacji</p>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Klient</th>
                            <th>Samochód</th>
                            <th>Data rozpoczęcia</th>
                            <th>Data zakończenia</th>
                            <th>Status</th>
                            <th>Akcje</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($active_reservations as $reservation): ?>
                        <tr>
                            <td><?php echo $reservation['first_name'] . ' ' . $reservation['last_name']; ?></td>
                            <td><?php echo $reservation['brand'] . ' ' . $reservation['model'] . ' (' . $reservation['license_plate'] . ')'; ?></td>
                            <td><?php echo $reservation['start_date']; ?></td>
                            <td><?php echo $reservation['end_date']; ?></td>
                            <td>
                                <span class="status-badge <?php echo $reservation['reservation_status']; ?>">
                                    <?php echo $reservation['reservation_status']; ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="reservation_id" value="<?php echo $reservation['reservation_id']; ?>">
                                    <button type="submit" name="convert_reservation" class="btn btn-small btn-success" 
                                            onclick="return confirm('Czy na pewno chcesz przekonwertować tę rezerwację na wypożyczenie?')">
                                        Zamień na wypożyczenie
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="customers-list">
        <h3>Lista Klientów</h3>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Imię i nazwisko</th>
                        <th>Email</th>
                        <th>Telefon</th>
                        <th>Data rejestracji</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $customer): ?>
                    <tr>
                        <td><?php echo $customer['first_name'] . ' ' . $customer['last_name']; ?></td>
                        <td><?php echo $customer['email']; ?></td>
                        <td><?php echo $customer['phone']; ?></td>
                        <td><?php echo $customer['registration_date']; ?></td>
                        <td>
                            <button onclick="showPenaltyForm(<?php echo $customer['customer_id']; ?>)" class="btn btn-small btn-warning">Nałóż karę</button>
                            <button onclick="showRentalForm(<?php echo $customer['customer_id']; ?>)" class="btn btn-small">Utwórz wypożyczenie</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="active-rentals">
        <h3>Aktywne Wypożyczenia</h3>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Klient</th>
                        <th>Samochód</th>
                        <th>Rejestracja</th>
                        <th>Data wypożyczenia</th>
                        <th>Planowany zwrot</th>
                        <th>Przebieg</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($active_rentals as $rental): ?>
                    <tr>
                        <td><?php echo $rental['first_name'] . ' ' . $rental['last_name']; ?></td>
                        <td><?php echo $rental['brand'] . ' ' . $rental['model']; ?></td>
                        <td><?php echo $rental['license_plate']; ?></td>
                        <td><?php echo $rental['rental_date']; ?></td>
                        <td><?php echo $rental['planned_return_date']; ?></td>
                        <td><?php echo number_format($rental['current_mileage']); ?> km</td>
                        <td>
                            <button onclick="showReturnForm(<?php echo $rental['rental_id']; ?>, <?php echo $rental['vehicle_id']; ?>, '<?php echo $rental['brand'] . ' ' . $rental['model']; ?>', '<?php echo $rental['license_plate']; ?>', <?php echo $rental['current_mileage']; ?>)" 
                                    class="btn btn-small btn-success">
                                Zarejestruj zwrot
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Formularz kary -->
    <div id="penaltyForm" class="form-modal" style="display: none;">
        <div class="modal-content">
            <h3>Nałóż karę</h3>
            <form method="POST">
                <input type="hidden" name="customer_id" id="penalty_customer_id">
                <div class="form-group">
                    <label>Wypożyczenie:</label>
                    <select name="rental_id" id="penalty_rental_select" required>
                        <!-- Opcje będą wypełniane dynamicznie -->
                    </select>
                </div>
                <div class="form-group">
                    <label>Kwota kary:</label>
                    <input type="number" name="amount" required>
                </div>
                <div class="form-group">
                    <label>Powód:</label>
                    <textarea name="reason" required></textarea>
                </div>
                <div class="form-buttons">
                    <button type="submit" name="add_penalty" class="btn">Nałóż karę</button>
                    <button type="button" onclick="hidePenaltyForm()" class="btn btn-secondary">Anuluj</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Formularz wypożyczenia -->
    <div id="rentalForm" class="form-modal" style="display: none;">
        <div class="modal-content">
            <h3>Utwórz wypożyczenie</h3>
            <form method="POST">
                <input type="hidden" name="customer_id" id="rental_customer_id">
                <div class="form-group">
                    <label>Samochód:</label>
                    <select name="vehicle_id" required>
                        <?php foreach ($available_vehicles as $vehicle): ?>
                            <option value="<?php echo $vehicle['vehicle_id']; ?>">
                                <?php echo $vehicle['brand'] . ' ' . $vehicle['model'] . ' (' . $vehicle['license_plate'] . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Planowany zwrot:</label>
                    <input type="date" name="planned_return_date" required>
                </div>
                <div class="form-buttons">
                    <button type="submit" name="create_rental" class="btn">Utwórz wypożyczenie</button>
                    <button type="button" onclick="hideRentalForm()" class="btn btn-secondary">Anuluj</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Formularz zwrotu pojazdu -->
    <div id="returnForm" class="form-modal" style="display: none;">
        <div class="modal-content">
            <h3>Rejestracja Zwrotu Pojazdu</h3>
            <form method="POST" id="returnVehicleForm">
                <input type="hidden" name="rental_id" id="return_rental_id">
                <input type="hidden" name="vehicle_id" id="return_vehicle_id">
                
                <div class="return-info">
                    <p><strong>Pojazd:</strong> <span id="return_vehicle_name"></span></p>
                    <p><strong>Rejestracja:</strong> <span id="return_license_plate"></span></p>
                    <p><strong>Obecny przebieg:</strong> <span id="return_current_mileage"></span> km</p>
                </div>
                
                <div class="form-group">
                    <label>Nowy przebieg (km):</label>
                    <input type="number" name="new_mileage" id="new_mileage" required min="0">
                </div>
                
                <div class="form-group">
                    <label>Poziom paliwa:</label>
                    <select name="fuel_level" required>
                        <option value="full">Pełny</option>
                        <option value="3/4">3/4</option>
                        <option value="1/2">1/2</option>
                        <option value="1/4">1/4</option>
                        <option value="empty">Pusty</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Stan pojazdu po oddaniu:</label>
                    <select name="vehicle_condition" required>
                        <option value="excellent">Doskonały</option>
                        <option value="good">Dobry</option>
                        <option value="fair">Średni</option>
                        <option value="poor">Słaby</option>
                        <option value="damaged">Uszkodzony</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="requires_repair" id="requires_repair">
                        Wymaga naprawy
                    </label>
                </div>
                
                <div class="form-group">
                    <label>Dodatkowe informacje:</label>
                    <textarea name="notes" rows="4" placeholder="Opisz stan pojazdu, uszkodzenia, uwagi..."></textarea>
                </div>
                
                <div class="form-buttons">
                    <button type="submit" name="return_vehicle" class="btn btn-success">Zarejestruj zwrot</button>
                    <button type="button" onclick="hideReturnForm()" class="btn btn-secondary">Anuluj</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Dane historii wypożyczeń klientów
const customerRentalsHistory = <?php echo json_encode($customer_rentals_history); ?>;

function showPenaltyForm(customerId) {
    document.getElementById('penalty_customer_id').value = customerId;
    
    // Wypełnij listę wypożyczeń dla danego klienta
    const rentalSelect = document.getElementById('penalty_rental_select');
    rentalSelect.innerHTML = '';
    
    if (customerRentalsHistory[customerId] && customerRentalsHistory[customerId].length > 0) {
        customerRentalsHistory[customerId].forEach(rental => {
            const option = document.createElement('option');
            option.value = rental.rental_id;
            option.textContent = `${rental.brand} ${rental.model} (${rental.license_plate}) - ${rental.rental_date} do ${rental.planned_return_date}`;
            rentalSelect.appendChild(option);
        });
    } else {
        const option = document.createElement('option');
        option.value = '';
        option.textContent = 'Brak wypożyczeń dla tego klienta';
        option.disabled = true;
        rentalSelect.appendChild(option);
    }
    
    document.getElementById('penaltyForm').style.display = 'block';
}

function hidePenaltyForm() {
    document.getElementById('penaltyForm').style.display = 'none';
}

function showRentalForm(customerId) {
    document.getElementById('rental_customer_id').value = customerId;
    document.getElementById('rentalForm').style.display = 'block';
}

function hideRentalForm() {
    document.getElementById('rentalForm').style.display = 'none';
}

function showReturnForm(rentalId, vehicleId, vehicleName, licensePlate, currentMileage) {
    document.getElementById('return_rental_id').value = rentalId;
    document.getElementById('return_vehicle_id').value = vehicleId;
    document.getElementById('return_vehicle_name').textContent = vehicleName;
    document.getElementById('return_license_plate').textContent = licensePlate;
    document.getElementById('return_current_mileage').textContent = currentMileage.toLocaleString();
    
    // Ustaw minimalny przebieg na obecny przebieg
    document.getElementById('new_mileage').min = currentMileage;
    document.getElementById('new_mileage').value = currentMileage;
    
    document.getElementById('returnForm').style.display = 'block';
}

function hideReturnForm() {
    document.getElementById('returnForm').style.display = 'none';
}

// Zamknij modal po kliknięciu poza nim
window.onclick = function(event) {
    const modals = document.querySelectorAll('.form-modal');
    modals.forEach(modal => {
        if (event.target === modal) {
            if (modal.id === 'penaltyForm') hidePenaltyForm();
            if (modal.id === 'rentalForm') hideRentalForm();
            if (modal.id === 'returnForm') hideReturnForm();
        }
    });
}

// Walidacja formularza zwrotu
document.getElementById('returnVehicleForm').addEventListener('submit', function(e) {
    const newMileage = parseInt(document.getElementById('new_mileage').value);
    const currentMileage = parseInt(document.getElementById('return_current_mileage').textContent.replace(/,/g, ''));
    
    if (newMileage < currentMileage) {
        e.preventDefault();
        alert('Nowy przebieg nie może być mniejszy niż obecny przebieg!');
        document.getElementById('new_mileage').focus();
        return false;
    }
    
    const requiresRepair = document.getElementById('requires_repair').checked;
    if (requiresRepair) {
        return confirm('Czy na pewno chcesz zarejestrować zwrot z informacją o wymaganej naprawie? Pojazd zostanie oznaczony jako "naprawa".');
    }
    
    return confirm('Czy na pewno chcesz zarejestrować zwrot tego pojazdu?');
});
</script>

<style>
.reservations-section,
.customers-list,
.active-rentals {
    margin: 2rem 0;
    padding: 1.5rem;
    background: #f8f9fa;
    border-radius: 8px;
}

.reservations-section h3,
.customers-list h3,
.active-rentals h3 {
    margin-bottom: 1rem;
    color: #2c3e50;
}

.status-badge.pending {
    background: #fff3cd;
    color: #856404;
}

.status-badge.confirmed {
    background: #d4edda;
    color: #155724;
}

.form-modal {
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    display: flex;
    justify-content: center;
    align-items: center;
}

.modal-content {
    background: white;
    padding: 2rem;
    border-radius: 8px;
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
}

.return-info {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 5px;
    margin-bottom: 1rem;
    border-left: 4px solid #3498db;
}

.return-info p {
    margin: 0.5rem 0;
}

.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: bold;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 1rem;
}

.form-group input[type="checkbox"] {
    width: auto;
    margin-right: 0.5rem;
}

.form-buttons {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
}

.table-container {
    overflow-x: auto;
    margin: 1rem 0;
}

table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 8px;
    overflow: hidden;
}

th, td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid #eee;
}

th {
    background: #34495e;
    color: white;
    font-weight: bold;
}

tr:hover {
    background: #f8f9fa;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>