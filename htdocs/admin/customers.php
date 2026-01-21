<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
redirectIfNotAdmin();

$database = new Database();
$db = $database->getConnection();

$search_surname = trim($_GET['surname'] ?? '');
$search_plate = trim($_GET['plate'] ?? '');

// Obsługa nakładania kary (RĘCZNEJ)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_penalty'])) {
    $customer_id = $_POST['customer_id'] ?? null;
    $amount = $_POST['amount'];
    $reason = $_POST['reason'];
    $rental_id = $_POST['rental_id'];
    
    // Jeśli brak customer_id, próbujemy pobrać z wypożyczenia
    if ($rental_id && !$customer_id) {
        $getCustomerIdQuery = "SELECT customer_id FROM Rental WHERE rental_id = ?";
        $stmt_cust = $db->prepare($getCustomerIdQuery);
        $stmt_cust->execute([$rental_id]);
        $result = $stmt_cust->fetch(PDO::FETCH_ASSOC);
        if ($result) $customer_id = $result['customer_id'];
    }
    
    if ($customer_id) {
        // ZMIANA: Usunięto customer_id z INSERT INTO (Naprawa błędu SQL)
        // Zakładamy, że tabela Penalty nie ma kolumny customer_id, skoro występował błąd
        $query = "INSERT INTO Penalty (amount, reason, imposition_date, payment_deadline, rental_id, penalty_status) 
                  VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY), ?, 'pending')";
        $stmt = $db->prepare($query);
        // ZMIANA: Usunięto $customer_id z parametrów execute
        if ($stmt->execute([$amount, $reason, $rental_id])) {
            
            // ZMIANA: Wysyłanie maila o karze
            require_once __DIR__ . '/../includes/email_sender.php';
            $stmt_user = $db->prepare("SELECT email, first_name FROM Customer WHERE customer_id = ?");
            $stmt_user->execute([$customer_id]);
            $user = $stmt_user->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                sendPenaltyNotificationEmail($user['email'], $user['first_name'], $amount, $reason);
            }

            $success = "Kara została nałożona i powiadomienie wysłane!";
        } else {
            $error = "Błąd podczas nakładania kary!";
        }
    } else {
        $error = "Błąd: Nie można zidentyfikować klienta dla kary!";
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
        $stmt2 = $db->prepare("UPDATE Vehicle SET status = 'rented' WHERE vehicle_id = ?");
        $stmt2->execute([$vehicle_id]);
        $success = "Wypożyczenie zostało utworzone!";
    } else {
        $error = "Błąd podczas tworzenia wypożyczenia!";
    }
}

// Konwersja rezerwacji
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['convert_reservation'])) {
    $reservation_id = $_POST['reservation_id'];
    try {
        $db->beginTransaction();
        $reservation = $db->query("SELECT * FROM Reservation WHERE reservation_id = $reservation_id")->fetch(PDO::FETCH_ASSOC);
        if ($reservation) {
            $stmt = $db->prepare("INSERT INTO Rental (planned_return_date, rental_cost, deposit, customer_id, vehicle_id, pickup_location_id, return_location_id, reservation_id, rental_status) VALUES (?, 100, 500, ?, ?, 1, 1, ?, 'active')");
            $stmt->execute([$reservation['end_date'], $reservation['customer_id'], $reservation['vehicle_id'], $reservation_id]);
            
            $db->prepare("UPDATE Reservation SET reservation_status = 'completed' WHERE reservation_id = ?")->execute([$reservation_id]);
            $db->prepare("UPDATE Vehicle SET status = 'rented' WHERE vehicle_id = ?")->execute([$reservation['vehicle_id']]);
            $db->commit();
            $success = "Rezerwacja przekonwertowana!";
        }
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Błąd: " . $e->getMessage();
    }
}

// Rejestracja zwrotu
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['return_vehicle'])) {
    $rental_id = $_POST['rental_id'];
    $vehicle_id = $_POST['vehicle_id'];
    $fuel_level = $_POST['fuel_level'];
    $vehicle_condition = $_POST['vehicle_condition'];
    $requires_repair = isset($_POST['requires_repair']) ? 1 : 0;
    $notes = $_POST['notes'];
    $new_mileage = $_POST['new_mileage'];
    
    $penalty_amount = floatval($_POST['penalty_amount'] ?? 0);
    $penalty_reason = trim($_POST['penalty_reason'] ?? '');
    $success_penalty = '';
    
    try {
        $db->beginTransaction();
        
        // Aktualizacja wypożyczenia
        $db->prepare("UPDATE Rental SET actual_return_date = CURDATE(), rental_status = 'completed' WHERE rental_id = ?")->execute([$rental_id]);
        
        // Aktualizacja pojazdu
        $db->prepare("UPDATE Vehicle SET status = 'available', mileage = ?, technical_condition = ? WHERE vehicle_id = ?")->execute([$new_mileage, $vehicle_condition, $vehicle_id]);
        
        // Serwis
        if ($requires_repair) {
            $db->prepare("UPDATE Vehicle SET status = 'maintenance' WHERE vehicle_id = ?")->execute([$vehicle_id]);
            $db->prepare("INSERT INTO Service (service_type, start_date, service_status, mileage_at_service, description, vehicle_id, location_id) VALUES ('repair', CURDATE(), 'scheduled', ?, ?, ?, 1)")->execute([$new_mileage, $notes ?: 'Naprawa po zwrocie', $vehicle_id]);
        }

        // Kara
        if ($penalty_amount > 0 && !empty($penalty_reason)) {
            $stmt_cust = $db->prepare("SELECT customer_id FROM Rental WHERE rental_id = ?");
            $stmt_cust->execute([$rental_id]);
            $customer_id = $stmt_cust->fetchColumn();
            
            if ($customer_id) {
                // ZMIANA: Usunięto customer_id z INSERT INTO (Naprawa błędu SQL)
                $query_penalty = "INSERT INTO Penalty (amount, reason, imposition_date, payment_deadline, rental_id, penalty_status) 
                                  VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY), ?, 'pending')";
                $stmt_penalty = $db->prepare($query_penalty);
                // ZMIANA: Usunięto $customer_id z execute
                $stmt_penalty->execute([$penalty_amount, $penalty_reason, $rental_id]);
                
                // ZMIANA: Wysyłanie maila o karze
                require_once __DIR__ . '/../includes/email_sender.php';
                $stmt_user = $db->prepare("SELECT email, first_name FROM Customer WHERE customer_id = ?");
                $stmt_user->execute([$customer_id]);
                $user = $stmt_user->fetch(PDO::FETCH_ASSOC);
                if ($user) {
                    sendPenaltyNotificationEmail($user['email'], $user['first_name'], $penalty_amount, $penalty_reason);
                }

                $success_penalty = " i nałożono karę.";
            }
        }
        
        // Info o zwrocie
        try {
            $db->prepare("INSERT INTO VehicleReturnInfo (rental_id, fuel_level, vehicle_condition, requires_repair, notes, inspector_notes) VALUES (?, ?, ?, ?, ?, ?)")->execute([$rental_id, $fuel_level, $vehicle_condition, $requires_repair, $notes, 'Admin']);
        } catch (Exception $e) {}
        
        $db->commit();
        $success = "Zwrot zarejestrowany" . $success_penalty;
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Błąd: " . $e->getMessage();
    }
}

// Pobieranie danych (filtry)
$base_where_parts = []; $base_params = [];
if (!empty($search_surname)) { $base_where_parts[] = " c.last_name LIKE ?"; $base_params[] = "%$search_surname%"; }
if (!empty($search_plate)) { $base_where_parts[] = " v.license_plate LIKE ?"; $base_params[] = "%$search_plate%"; }
$base_where = !empty($base_where_parts) ? ' AND ' . implode(' AND ', $base_where_parts) : '';

// Klienci
$customer_where = ''; $customer_params = [];
if (!empty($search_surname)) { $customer_where = " WHERE last_name LIKE ?"; $customer_params = ["%$search_surname%"]; }

$customers = $db->prepare("SELECT * FROM Customer $customer_where ORDER BY registration_date DESC");
$customers->execute($customer_params);
$customers = $customers->fetchAll(PDO::FETCH_ASSOC);

// Wypożyczenia i Rezerwacje
$rentals = $db->prepare("SELECT r.*, c.customer_id, c.first_name, c.last_name, v.license_plate, vm.brand, vm.model, v.mileage as current_mileage FROM Rental r JOIN Customer c ON r.customer_id = c.customer_id JOIN Vehicle v ON r.vehicle_id = v.vehicle_id JOIN VehicleModel vm ON v.model_id = vm.model_id WHERE r.rental_status = 'active' $base_where");
$rentals->execute($base_params);
$active_rentals = $rentals->fetchAll(PDO::FETCH_ASSOC);

$reservations = $db->prepare("SELECT res.*, c.first_name, c.last_name, v.license_plate, vm.brand, vm.model FROM Reservation res JOIN Customer c ON res.customer_id = c.customer_id JOIN Vehicle v ON res.vehicle_id = v.vehicle_id JOIN VehicleModel vm ON v.model_id = vm.model_id WHERE res.reservation_status IN ('pending', 'confirmed') $base_where ORDER BY res.start_date ASC");
$reservations->execute($base_params);
$active_reservations = $reservations->fetchAll(PDO::FETCH_ASSOC);

$available_vehicles = $db->query("SELECT v.*, vm.brand, vm.model FROM Vehicle v JOIN VehicleModel vm ON v.model_id = vm.model_id WHERE v.status = 'available'")->fetchAll(PDO::FETCH_ASSOC);

// Historia dla kar
$customer_rentals_history = [];
foreach ($customers as $c) {
    $stmt = $db->prepare("SELECT r.*, v.license_plate, vm.brand, vm.model FROM Rental r JOIN Vehicle v ON r.vehicle_id = v.vehicle_id JOIN VehicleModel vm ON v.model_id = vm.model_id WHERE r.customer_id = ? ORDER BY r.rental_date DESC");
    $stmt->execute([$c['customer_id']]);
    $customer_rentals_history[$c['customer_id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>
<div class="dashboard">
    <h2>Zarządzanie Klientami</h2>

    <?php if (isset($success)): ?><div class="success"><?php echo $success; ?></div><?php endif; ?>
    <?php if (isset($error)): ?><div class="error"><?php echo $error; ?></div><?php endif; ?>

    <div class="search-form-container">
        <h3>Wyszukaj</h3>
        <form method="GET" class="search-form">
            <div class="form-group-inline">
                <label>Nazwisko:</label>
                <input type="text" name="surname" value="<?php echo htmlspecialchars($search_surname); ?>">
            </div>
            <div class="form-group-inline">
                <label>Rejestracja:</label>
                <input type="text" name="plate" value="<?php echo htmlspecialchars($search_plate); ?>">
            </div>
            <button type="submit" class="btn btn-primary">Filtruj</button>
            <a href="customers.php" class="btn btn-secondary">Wyczyść</a>
        </form>
    </div>

    <div class="section-container">
        <button class="collapsible"><h3>Aktywne Rezerwacje (<?php echo count($active_reservations); ?>)</h3></button>
        <div class="content">
            <div class="table-container">
                <table>
                    <thead><tr><th>Klient</th><th>Rejestracja</th><th>Pojazd</th><th>Od</th><th>Do</th><th>Akcje</th></tr></thead>
                    <tbody>
                    <?php foreach ($active_reservations as $res): ?>
                        <tr>
                            <td><?php echo $res['first_name'].' '.$res['last_name']; ?></td>
							<td><?php echo $res['license_plate']; ?></td>
                            <td><?php echo $res['brand'].' '.$res['model']; ?></td>
                            <td><?php echo $res['start_date']; ?></td>
                            <td><?php echo $res['end_date']; ?></td>
                            <td>
                                <form method="POST"><input type="hidden" name="reservation_id" value="<?php echo $res['reservation_id']; ?>"><button name="convert_reservation" class="btn btn-small btn-success">Wypożycz</button></form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="section-container">
        <button class="collapsible"><h3>Klienci (<?php echo count($customers); ?>)</h3></button>
        <div class="content">
            <div class="table-container">
                <table>
                    <thead><tr><th>Imię i nazwisko</th><th>Email</th><th>Telefon</th><th>Akcje</th></tr></thead>
                    <tbody>
                    <?php foreach ($customers as $cust): ?>
                        <tr>
                            <td><?php echo $cust['first_name'].' '.$cust['last_name']; ?></td>
                            <td><?php echo $cust['email']; ?></td>
                            <td><?php echo $cust['phone']; ?></td>
                            <td>
                                <button onclick="showPenaltyForm(<?php echo $cust['customer_id']; ?>)" class="btn btn-small btn-warning">Kara</button>
                                <button onclick="showRentalForm(<?php echo $cust['customer_id']; ?>)" class="btn btn-small">Wypożycz</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="section-container">
        <button class="collapsible"><h3>Aktywne Wypożyczenia (<?php echo count($active_rentals); ?>)</h3></button>
        <div class="content">
            <div class="table-container">
                <table>
                    <thead><tr><th>Klient</th><th>Rejestracja</th><th>Pojazd</th><th>Data zwrotu</th><th>Akcje</th></tr></thead>
                    <tbody>
                    <?php foreach ($active_rentals as $rent): ?>
                        <tr>
                            <td><?php echo $rent['first_name'].' '.$rent['last_name']; ?></td>
                            <td><?php echo $rent['license_plate']; ?></td>
                            <td><?php echo $rent['brand'].' '.$rent['model']; ?></td>
                            <td><?php echo $rent['planned_return_date']; ?></td>
                            <td>
                                <button onclick="showReturnForm(<?php echo $rent['rental_id']; ?>, <?php echo $rent['vehicle_id']; ?>, '<?php echo $rent['brand'].' '.$rent['model']; ?>', '<?php echo $rent['license_plate']; ?>', <?php echo $rent['current_mileage']; ?>)" class="btn btn-small btn-success">Zwrot</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="penaltyForm" class="form-modal" style="display:none;">
        <div class="modal-content">
            <h3>Nałóż karę</h3>
            <form method="POST">
                <input type="hidden" name="customer_id" id="penalty_customer_id">
                <select name="rental_id" id="penalty_rental_select" required></select>
                <input type="number" name="amount" placeholder="Kwota" required step="0.01">
                <textarea name="reason" placeholder="Powód" required></textarea>
                <button type="submit" name="add_penalty" class="btn">Zatwierdź</button>
                <button type="button" onclick="document.getElementById('penaltyForm').style.display='none'" class="btn btn-secondary">Anuluj</button>
            </form>
        </div>
    </div>

    <div id="rentalForm" class="form-modal" style="display:none;">
        <div class="modal-content">
            <h3>Nowe wypożyczenie</h3>
            <form method="POST">
                <input type="hidden" name="customer_id" id="rental_customer_id">
                <select name="vehicle_id" required>
                    <?php foreach ($available_vehicles as $v) echo "<option value='{$v['vehicle_id']}'>{$v['brand']} {$v['model']} ({$v['license_plate']})</option>"; ?>
                </select>
                <input type="date" name="planned_return_date" required>
                <button type="submit" name="create_rental" class="btn">Utwórz</button>
                <button type="button" onclick="document.getElementById('rentalForm').style.display='none'" class="btn btn-secondary">Anuluj</button>
            </form>
        </div>
    </div>

    <div id="returnForm" class="form-modal" style="display:none;">
        <div class="modal-content">
            <h3>Rejestracja Zwrotu</h3>
            <form method="POST" id="returnVehicleForm">
                <input type="hidden" name="rental_id" id="return_rental_id">
                <input type="hidden" name="vehicle_id" id="return_vehicle_id">
                <p>Pojazd: <span id="return_vehicle_name"></span></p>
                <p>Obecny przebieg: <span id="return_current_mileage"></span> km</p>
                <label>Nowy przebieg:</label>
                <input type="number" name="new_mileage" id="new_mileage" required>
                <label>Paliwo:</label>
                <select name="fuel_level"><option value="full">Pełny</option><option value="3/4">3/4</option><option value="1/2">1/2</option><option value="1/4">1/4</option><option value="empty">Pusty</option></select>
                <label>Stan:</label>
                <select name="vehicle_condition"><option value="excellent">Doskonały</option><option value="good">Dobry</option><option value="fair">Średni</option><option value="poor">Słaby</option></select>
                <label><input type="checkbox" name="requires_repair" id="requires_repair"> Wymaga naprawy</label>
                <textarea name="notes" placeholder="Notatki"></textarea>
                <hr>
                <label>Kara (opcjonalnie):</label>
                <input type="number" name="penalty_amount" id="penalty_amount_return" step="0.01" value="0">
                <textarea name="penalty_reason" id="penalty_reason_return" placeholder="Powód kary"></textarea>
                <button type="submit" name="return_vehicle" class="btn btn-success">Zapisz zwrot</button>
                <button type="button" onclick="document.getElementById('returnForm').style.display='none'" class="btn btn-secondary">Anuluj</button>
            </form>
        </div>
    </div>
</div>

<script>
const customerRentalsHistory = <?php echo json_encode($customer_rentals_history); ?>;
// Funkcje JS zachowane z oryginału (skrócone)
function showPenaltyForm(cid) {
    document.getElementById('penalty_customer_id').value = cid;
    const sel = document.getElementById('penalty_rental_select'); sel.innerHTML = '';
    if(customerRentalsHistory[cid]) customerRentalsHistory[cid].forEach(r => {
        sel.add(new Option(`${r.brand} ${r.model} (${r.rental_status})`, r.rental_id));
    });
    document.getElementById('penaltyForm').style.display = 'flex';
}
function showRentalForm(cid) { document.getElementById('rental_customer_id').value = cid; document.getElementById('rentalForm').style.display = 'flex'; }
function showReturnForm(rid, vid, name, plate, mile) {
    document.getElementById('return_rental_id').value = rid;
    document.getElementById('return_vehicle_id').value = vid;
    document.getElementById('return_vehicle_name').innerText = name;
    document.getElementById('return_current_mileage').innerText = mile;
    document.getElementById('new_mileage').min = mile;
    document.getElementById('new_mileage').value = mile;
    document.getElementById('returnForm').style.display = 'flex';
}
// Obsługa akordeonu
document.querySelectorAll('.collapsible').forEach(c => c.addEventListener('click', function() {
    this.classList.toggle('active-collapsible');
    var content = this.nextElementSibling;
    content.style.maxHeight = content.style.maxHeight ? null : content.scrollHeight + "px";
}));
</script>
<style>
.section-container { margin: 2rem 0; border: 1px solid #ddd; border-radius: 8px; overflow: hidden; }
.collapsible { background: #34495e; color: white; padding: 15px; width: 100%; border: none; text-align: left; cursor: pointer; }
.content { max-height: 0; overflow: hidden; transition: max-height 0.3s ease-out; background: #f8f9fa; }
.form-modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; justify-content: center; align-items: center; z-index: 999;}
.modal-content { background: white; padding: 20px; border-radius: 8px; width: 90%; max-width: 500px; max-height: 90vh; overflow-y: auto; }
</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>