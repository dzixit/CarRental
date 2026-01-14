<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
redirectIfNotAdmin();

$database = new Database();
$db = $database->getConnection();

// --- PRZYGOTOWANIE FILTRÓW WYSZUKIWANIA ---
// Zmieniamy nazwę na 'surname', aby odzwierciedlić filtrowanie tylko po nazwisku.
$search_surname = trim($_GET['surname'] ?? '');
$search_plate = trim($_GET['plate'] ?? '');

// Obsługa nakładania kary z poziomu "Listy Klientów"
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_penalty'])) {
    $customer_id = $_POST['customer_id'] ?? null;
    $amount = $_POST['amount'];
    $reason = $_POST['reason'];
    $rental_id = $_POST['rental_id'];
    
    if ($rental_id && !$customer_id) {
        $getCustomerIdQuery = "SELECT customer_id FROM Rental WHERE rental_id = ?";
        $stmt_cust = $db->prepare($getCustomerIdQuery);
        $stmt_cust->execute([$rental_id]);
        $result = $stmt_cust->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $customer_id = $result['customer_id'];
        }
    }
    
    if ($customer_id) {
        $query = "INSERT INTO Penalty (amount, reason, imposition_date, payment_deadline, rental_id, customer_id, penalty_status) 
                  VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY), ?, ?, 'pending')";
        $stmt = $db->prepare($query);
        if ($stmt->execute([$amount, $reason, $rental_id, $customer_id])) {
            $success = "Kara została nałożona!";
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

// Rejestracja zwrotu z dodatkowymi informacjami ORAZ OPCJONALNĄ KARĄ
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['return_vehicle'])) {
    $rental_id = $_POST['rental_id'];
    $vehicle_id = $_POST['vehicle_id'];
    $fuel_level = $_POST['fuel_level'];
    $vehicle_condition = $_POST['vehicle_condition'];
    $requires_repair = isset($_POST['requires_repair']) ? 1 : 0;
    $notes = $_POST['notes'];
    $new_mileage = $_POST['new_mileage'];
    
    // Nowe opcjonalne pola dla kary
    $penalty_amount = floatval($_POST['penalty_amount'] ?? 0);
    $penalty_reason = trim($_POST['penalty_reason'] ?? '');
    $success_penalty = '';
    $error_penalty = '';
    
    try {
        $db->beginTransaction();
        
        // 1. Zaktualizuj wypożyczenie (status na completed)
        $update_rental = "UPDATE Rental SET actual_return_date = CURDATE(), rental_status = 'completed' WHERE rental_id = ?";
        $stmt = $db->prepare($update_rental);
        $stmt->execute([$rental_id]);
        
        // 2. Zaktualizuj pojazd (status, przebieg, stan techniczny)
        $update_vehicle = "UPDATE Vehicle SET 
                             status = 'available', 
                             mileage = ?,
                             technical_condition = ?
                             WHERE vehicle_id = ?";
        $stmt = $db->prepare($update_vehicle);
        $stmt->execute([$new_mileage, $vehicle_condition, $vehicle_id]);
        
        // 3. Jeśli wymaga naprawy, zmień status na maintenance
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

        // 4. Opcjonalnie nałóż karę, jeśli Kwota > 0 i jest powód
        if ($penalty_amount > 0 && !empty($penalty_reason)) {
            // Pobierz customer_id z Rental
            $getCustomerIdQuery = "SELECT customer_id FROM Rental WHERE rental_id = ?";
            $stmt_cust = $db->prepare($getCustomerIdQuery);
            $stmt_cust->execute([$rental_id]);
            $result = $stmt_cust->fetch(PDO::FETCH_ASSOC);
            $customer_id = $result['customer_id'] ?? null;
            
            if ($customer_id) {
                $query_penalty = "INSERT INTO Penalty (amount, reason, imposition_date, payment_deadline, rental_id, customer_id, penalty_status) 
                                  VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY), ?, ?, 'pending')";
                $stmt_penalty = $db->prepare($query_penalty);
                $stmt_penalty->execute([$penalty_amount, $penalty_reason, $rental_id, $customer_id]);
                $success_penalty = " i nałożono karę w wysokości " . number_format($penalty_amount, 2) . " PLN";
            } else {
                $error_penalty = "Nie można nałożyć kary (brak Customer ID). ";
            }
        }
        
        // 5. Zapisz dodatkowe informacje o zwrocie
        try {
            $return_info_query = "INSERT INTO VehicleReturnInfo (rental_id, fuel_level, vehicle_condition, requires_repair, notes, inspector_notes) 
                                  VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($return_info_query);
            $stmt->execute([$rental_id, $fuel_level, $vehicle_condition, $requires_repair, $notes, 'Zwrot zarejestrowany przez admina']);
        } catch (Exception $e) {
            // Tabela może nie istnieć, ignoruj błąd
        }
        
        $db->commit();
        $success = "Zwrot samochodu został zarejestrowany z pełnymi informacjami!" . $success_penalty . $error_penalty;
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Błąd podczas rejestracji zwrotu: " . $e->getMessage();
    }
}

// --- POBIERANIE DANYCH Z UJEDNOLICZONYM FILTROWANIEM ---

// 1. Warunki wspólne dla Rezerwacji i Wypożyczeń (dla JOIN na Customer i Vehicle)
$base_where_parts = [];
$base_params = [];

if (!empty($search_surname)) {
    // Filtr tylko po Nazwisku
    $base_where_parts[] = " c.last_name LIKE ?";
    $base_params[] = "%$search_surname%";
}

if (!empty($search_plate)) {
    // Filtr po Rejestracji
    $base_where_parts[] = " v.license_plate LIKE ?";
    $base_params[] = "%$search_plate%";
}
$base_where = !empty($base_where_parts) ? ' AND ' . implode(' AND ', $base_where_parts) : '';


// 2. Pobierz klientów (filtrowanie po nazwisku oraz po rejestracji, jeśli ma aktywne powiązanie)
$customer_where = '';
$customer_params = [];

// Jeśli jest filtr po nazwisku, filtrujemy bezpośrednio
if (!empty($search_surname)) {
    $customer_where = " WHERE last_name LIKE ?";
    $customer_params = ["%$search_surname%"];
}

// Jeśli jest filtr po rejestracji, musimy użyć podzapytania (JOIN) do Rental/Reservation
if (!empty($search_plate)) {
    $plate_join_where = " v.license_plate LIKE ?";
    
    // Zapytanie do identyfikacji Klientów powiązanych z daną rejestracją w aktywnych transakcjach.
    $customer_subquery = "
        SELECT DISTINCT c.customer_id FROM Customer c 
        INNER JOIN Rental r ON c.customer_id = r.customer_id
        INNER JOIN Vehicle v ON r.vehicle_id = v.vehicle_id
        WHERE r.rental_status = 'active' AND {$plate_join_where}
        UNION
        SELECT DISTINCT c.customer_id FROM Customer c 
        INNER JOIN Reservation res ON c.customer_id = res.customer_id
        INNER JOIN Vehicle v ON res.vehicle_id = v.vehicle_id
        WHERE res.reservation_status IN ('pending', 'confirmed') AND {$plate_join_where}
    ";
    
    // Parametry dla podzapytania (dwukrotnie, dla UNION)
    $plate_params_for_customers = ["%$search_plate%", "%$search_plate%"];

    // Jeśli był już filtr po nazwisku, dodajemy go jako warunek WHERE (customer_id IN ...)
    if (!empty($customer_where)) {
        // Mamy już $customer_where (Nazwisko). Dodajemy warunek dla rejestracji.
        $customer_where .= " AND customer_id IN ({$customer_subquery})";
        // Łączymy parametry: [Nazwisko, Rejestracja, Rejestracja]
        $customer_params = array_merge($customer_params, $plate_params_for_customers);
    } else {
        // Brak filtra po Nazwisku. Stosujemy tylko filtr rejestracji.
        $customer_where = " WHERE customer_id IN ({$customer_subquery})";
        $customer_params = $plate_params_for_customers;
    }
}


$customers_query = "SELECT * FROM Customer" . $customer_where . " ORDER BY registration_date DESC";
$stmt_customers = $db->prepare($customers_query);
$stmt_customers->execute($customer_params);
$customers = $stmt_customers->fetchAll(PDO::FETCH_ASSOC);


// 3. Pobierz aktywne wypożyczenia (filtrowanie po nazwisku i rejestracji)
$rental_where = " r.rental_status = 'active'";
$active_rentals_query = "SELECT r.*, c.customer_id, c.first_name, c.last_name, v.license_plate, vm.brand, vm.model, v.mileage as current_mileage 
                         FROM Rental r 
                         JOIN Customer c ON r.customer_id = c.customer_id 
                         JOIN Vehicle v ON r.vehicle_id = v.vehicle_id 
                         JOIN VehicleModel vm ON v.model_id = vm.model_id 
                         WHERE" . $rental_where . $base_where;

$stmt_rentals = $db->prepare($active_rentals_query);
$stmt_rentals->execute($base_params);
$active_rentals = $stmt_rentals->fetchAll(PDO::FETCH_ASSOC);


// 4. Pobierz aktywne rezerwacje (filtrowanie po nazwisku i rejestracji)
$reservation_where = " res.reservation_status IN ('pending', 'confirmed')";
$active_reservations_query = "SELECT res.*, c.first_name, c.last_name, v.license_plate, vm.brand, vm.model 
                               FROM Reservation res 
                               JOIN Customer c ON res.customer_id = c.customer_id 
                               JOIN Vehicle v ON res.vehicle_id = v.vehicle_id 
                               JOIN VehicleModel vm ON v.model_id = vm.model_id 
                               WHERE" . $reservation_where . $base_where . " ORDER BY res.start_date ASC";

$stmt_reservations = $db->prepare($active_reservations_query);
$stmt_reservations->execute($base_params);
$active_reservations = $stmt_reservations->fetchAll(PDO::FETCH_ASSOC);


// 5. Pobierz dostępne samochody (bez zmian)
$available_vehicles = $db->query("SELECT v.*, vm.brand, vm.model 
                                  FROM Vehicle v 
                                  JOIN VehicleModel vm ON v.model_id = vm.model_id 
                                  WHERE v.status = 'available'")->fetchAll(PDO::FETCH_ASSOC);

// 6. Pobierz historię wypożyczeń dla kar (tylko dla gefiltrowanej listy klientów)
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

    <div class="search-form-container">
        <h3>Wyszukaj</h3>
        <form method="GET" class="search-form">
            <div class="form-group-inline">
                <label for="search_surname">Filtruj po Nazwisku Klienta:</label>
                <input type="text" id="search_surname" name="surname" placeholder="Wprowadź nazwisko" value="<?php echo htmlspecialchars($search_surname); ?>">
            </div>
            <div class="form-group-inline">
                <label for="search_plate">Filtruj po Rejestracji Pojazdu:</label>
                <input type="text" id="search_plate" name="plate" placeholder="Wprowadź rejestrację" value="<?php echo htmlspecialchars($search_plate); ?>">
            </div>
            <button type="submit" class="btn btn-primary">Filtruj</button>
            <?php if (!empty($search_surname) || !empty($search_plate)): ?>
                <a href="customers.php" class="btn btn-secondary">Wyczyść filtry</a>
            <?php endif; ?>
        </form>
    </div>
    <hr>


    <div class="section-container">
        <button class="collapsible"><h3>Aktywne Rezerwacje Klientów (<?php echo count($active_reservations); ?>)</h3></button>
        <div class="content">
            <?php if (empty($active_reservations)): ?>
                <p>Brak aktywnych rezerwacji pasujących do podanych kryteriów.</p>
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
    </div>

    <div class="section-container">
        <button class="collapsible"><h3>Lista Klientów (<?php echo count($customers); ?>)</h3></button>
        <div class="content">
            <?php if (empty($customers)): ?>
                <p>Brak klientów pasujących do podanych kryteriów.</p>
            <?php else: ?>
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
            <?php endif; ?>
        </div>
    </div>

    <div class="section-container">
        <button class="collapsible"><h3>Aktywne Wypożyczenia (<?php echo count($active_rentals); ?>)</h3></button>
        <div class="content">
            <?php if (empty($active_rentals)): ?>
                <p>Brak aktywnych wypożyczeń pasujących do podanych kryteriów.</p>
            <?php else: ?>
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
            <?php endif; ?>
        </div>
    </div>

    <div id="penaltyForm" class="form-modal" style="display: none;">
        <div class="modal-content">
            <h3>Nałóż karę na Klienta</h3>
            <form method="POST">
                <input type="hidden" name="customer_id" id="penalty_customer_id"> 
                <div class="form-group">
                    <label>Wypożyczenie:</label>
                    <select name="rental_id" id="penalty_rental_select" required>
                        </select>
                </div>
                <div class="form-group">
                    <label>Kwota kary:</label>
                    <input type="number" name="amount" required step="0.01" min="0">
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

    <div id="returnForm" class="form-modal" style="display: none;">
        <div class="modal-content">
            <h3>Rejestracja Zwrotu Pojazdu i Inspekcja</h3>
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
                    <label>Dodatkowe informacje (Notatki inspektora):</label>
                    <textarea name="notes" rows="4" placeholder="Opisz stan pojazdu, uszkodzenia, uwagi..."></textarea>
                </div>
                
                <hr style="margin: 1.5rem 0;">
                <h4>Opcjonalna Kara/Dodatkowa Opłata</h4>

                <div class="form-group">
                    <label>Kwota Kary (PLN) (Opcjonalnie):</label>
                    <input type="number" name="penalty_amount" id="penalty_amount_return" step="0.01" min="0" value="0" placeholder="Wprowadź 0, jeśli brak kary">
                </div>
                
                <div class="form-group" id="penalty_reason_group" style="display:none;">
                    <label>Powód nałożenia kary:</label>
                    <textarea name="penalty_reason" id="penalty_reason_return" rows="2" placeholder="Np. brak paliwa, drobne uszkodzenie, przekroczenie limitu kilometrów..."></textarea>
                </div>
                <div class="form-buttons">
                    <button type="submit" name="return_vehicle" class="btn btn-success">Zarejestruj zwrot i Zakończ</button>
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
            const status = rental.rental_status === 'active' ? ' (Aktywne)' : ' (Zakończone)'; 
            option.textContent = `${rental.brand} ${rental.model} (${rental.license_plate}) - ${rental.rental_date} do ${rental.planned_return_date} ${status}`;
            rentalSelect.appendChild(option);
        });
        rentalSelect.disabled = false;
    } else {
        const option = document.createElement('option');
        option.value = '';
        option.textContent = 'Brak wypożyczeń dla tego klienta';
        option.disabled = true;
        rentalSelect.appendChild(option);
        rentalSelect.disabled = true;
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

    // Resetowanie pól kary
    document.getElementById('penalty_amount_return').value = 0;
    document.getElementById('penalty_reason_return').value = '';
    document.getElementById('penalty_reason_group').style.display = 'none';
    document.getElementById('penalty_reason_return').removeAttribute('required');

    document.getElementById('returnForm').style.display = 'block';
}

function hideReturnForm() {
    document.getElementById('returnForm').style.display = 'none';
}

// Funkcjonalność: Dynamiczne ukrywanie/pokazywanie pola powodu kary
document.addEventListener('DOMContentLoaded', () => {
    const penaltyAmountInput = document.getElementById('penalty_amount_return');
    const penaltyReasonGroup = document.getElementById('penalty_reason_group');
    const penaltyReasonTextarea = document.getElementById('penalty_reason_return');
    
    const togglePenaltyReason = () => {
        const amount = parseFloat(penaltyAmountInput.value) || 0;
        if (amount > 0) {
            penaltyReasonGroup.style.display = 'block';
            penaltyReasonTextarea.setAttribute('required', 'required');
        } else {
            penaltyReasonGroup.style.display = 'none';
            penaltyReasonTextarea.removeAttribute('required');
            penaltyReasonTextarea.value = ''; // Wyczyść powód, jeśli kwota = 0
        }
    };

    if (penaltyAmountInput) {
        penaltyAmountInput.addEventListener('input', togglePenaltyReason);
        // Ustaw stan początkowy po załadowaniu
        togglePenaltyReason();
    }
});


// Walidacja formularza zwrotu
document.getElementById('returnVehicleForm').addEventListener('submit', function(e) {
    const newMileage = parseInt(document.getElementById('new_mileage').value);
    const currentMileageElement = document.getElementById('return_current_mileage');
    // Usuń z formatowania lokalnego, aby uzyskać czystą liczbę
    const currentMileage = parseInt(currentMileageElement.textContent.replace(/[^\d]/g, '')); 
    
    // Walidacja przebiegu
    if (newMileage < currentMileage) {
        e.preventDefault();
        alert('Nowy przebieg nie może być mniejszy niż obecny przebieg!');
        document.getElementById('new_mileage').focus();
        return false;
    }

    // Walidacja opcjonalnej kary
    const penaltyAmount = parseFloat(document.getElementById('penalty_amount_return').value) || 0;
    const penaltyReason = document.getElementById('penalty_reason_return').value.trim();

    if (penaltyAmount > 0 && penaltyReason === '') {
        e.preventDefault();
        alert('Wprowadzono kwotę kary. Proszę podać powód jej nałożenia.');
        document.getElementById('penalty_reason_return').focus();
        return false;
    }
    
    let confirmationMessage = 'Czy na pewno chcesz zarejestrować zwrot tego pojazdu?';
    
    if (penaltyAmount > 0) {
        confirmationMessage += `\nUWAGA: Zostanie nałożona kara w wysokości ${penaltyAmount.toFixed(2)} PLN.`;
    }
    
    const requiresRepair = document.getElementById('requires_repair').checked;
    if (requiresRepair) {
        confirmationMessage += '\nUWAGA: Pojazd zostanie oznaczony jako "Wymaga naprawy".';
    }

    return confirm(confirmationMessage);
});

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


// Funkcjonalność akordeonu (rozwijanych sekcji)
const collapsibles = document.querySelectorAll('.collapsible');

collapsibles.forEach(collapsible => {
    collapsible.addEventListener('click', function() {
        this.classList.toggle('active-collapsible');
        const content = this.nextElementSibling;
        if (content.style.maxHeight) {
            content.style.maxHeight = null;
        } else {
            // Ustaw wysokość, aby animacja działała płynnie
            content.style.maxHeight = content.scrollHeight + "px";
        } 
    });
});
</script>

<style>
/* Zmiany w CSS dla akordeonu */
.section-container {
    margin: 2rem 0;
    border-radius: 8px;
    border: 1px solid #ddd;
    overflow: hidden; 
}

.collapsible {
    background-color: #34495e; 
    color: white;
    cursor: pointer;
    padding: 18px;
    width: 100%;
    border: none;
    text-align: left;
    outline: none;
    font-size: 1.1em;
    transition: background-color 0.3s ease, border-radius 0.3s ease;
    border-radius: 8px 8px 0 0; 
}

.collapsible h3 {
    margin: 0;
    display: inline;
}

.collapsible:hover,
.collapsible.active-collapsible {
    background-color: #2c3e50; 
}

.content {
    padding: 0 18px;
    max-height: 0; 
    overflow: hidden;
    transition: max-height 0.3s ease-out; 
    background-color: #f8f9fa;
}

.content .table-container {
    margin: 1rem 0 1.5rem 0;
}

/* Nowe style dla formularza wyszukiwania */
.search-form-container {
    background: #f4f4f9;
    padding: 1.5rem;
    border-radius: 8px;
    margin-bottom: 2rem;
    border: 1px solid #ddd;
}

.search-form h3 {
    margin-top: 0;
    margin-bottom: 1rem;
    color: #34495e;
}

.search-form {
    display: flex;
    gap: 15px;
    align-items: flex-end;
}

.form-group-inline {
    flex-grow: 1;
}

.form-group-inline label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
    font-size: 0.9em;
}

.search-form input[type="text"] {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.search-form .btn-primary,
.search-form .btn-secondary {
    padding: 0.75rem 1.2rem;
    white-space: nowrap; 
}

/* Zdefiniowane przyciski */
.btn {
    padding: 0.75rem 1.2rem;
    border-radius: 4px;
    font-weight: bold;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    transition: background-color 0.2s;
    font-size: 0.9rem;
}

.btn-primary {
    background-color: #3498db;
    color: white;
    border: none;
}
.btn-primary:hover {
    background-color: #2980b9;
}

.btn-secondary {
    background-color: #bdc3c7;
    color: #333;
    border: none;
}
.btn-secondary:hover {
    background-color: #95a5a6;
}

.btn-success {
    background-color: #2ecc71;
    color: white;
    border: none;
}
.btn-success:hover {
    background-color: #27ae60;
}

.btn-warning {
    background-color: #f39c12;
    color: white;
    border: none;
}
.btn-warning:hover {
    background-color: #e67e22;
}

@media (max-width: 768px) {
    .search-form {
        flex-direction: column;
        align-items: stretch;
    }
    .search-form .btn-primary,
    .search-form .btn-secondary {
        width: 100%;
    }
}


/* Pozostałe style modalne */
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
    font-size: