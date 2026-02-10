<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
redirectIfNotCustomer();

$database = new Database();
$db = $database->getConnection();


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel_reservation'])) {
    $reservation_id = $_POST['reservation_id'];
    
    // Sprawdź czy rezerwacja należy do klienta i jest w statusie pending
    $check_res_query = "SELECT reservation_id FROM Reservation 
                        WHERE reservation_id = ? AND customer_id = ? AND reservation_status = 'pending'";
    $stmt_check = $db->prepare($check_res_query);
    $stmt_check->execute([$reservation_id, $_SESSION['customer_id']]);
    
    if ($stmt_check->rowCount() > 0) {
        $cancel_query = "UPDATE Reservation SET reservation_status = 'cancelled' WHERE reservation_id = ?";
        $stmt_cancel = $db->prepare($cancel_query);
        if ($stmt_cancel->execute([$reservation_id])) {
            $success = "Rezerwacja została pomyślnie anulowana.";
        } else {
            $error = "Wystąpił błąd podczas anulowania rezerwacji.";
        }
    } else {
        $error = "Nie można anulować tej rezerwacji (mogła zostać już przetworzona lub nie istnieje).";
    }
}


// Przedłużenie wypożyczenia
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['extend_rental'])) {
    $rental_id = $_POST['extend_rental_id_val']; // Zmieniono nazwę pola, aby uniknąć konfliktów
    $new_return_date = $_POST['new_return_date'];
    
    // Sprawdź czy pojazd jest dostępny w nowym terminie
    $check_availability_query = "SELECT rental_id FROM Rental 
                                WHERE vehicle_id = (SELECT vehicle_id FROM Rental WHERE rental_id = ?)
                                AND rental_status = 'active'
                                AND rental_id != ?
                                AND (planned_return_date BETWEEN (SELECT planned_return_date FROM Rental WHERE rental_id = ?) AND ?)";
    $check_stmt = $db->prepare($check_availability_query);
    $check_stmt->execute([$rental_id, $rental_id, $rental_id, $new_return_date]);
    
    if ($check_stmt->rowCount() > 0) {
        $error = "Pojazd nie jest dostępny w wybranym terminie!";
    } else {
        $extend_query = "UPDATE Rental SET planned_return_date = ? WHERE rental_id = ?";
        $extend_stmt = $db->prepare($extend_query);
        if ($extend_stmt->execute([$new_return_date, $rental_id])) {
            $success = "Wypożyczenie zostało przedłużone do " . $new_return_date;
        } else {
            $error = "Błąd podczas przedłużania wypożyczenia!";
        }
    }
}

// Pobierz kary klienta (suma)
$penalties_sum_query = "SELECT SUM(amount) as total_penalties FROM Penalty p 
                       JOIN Rental r ON p.rental_id = r.rental_id 
                       WHERE r.customer_id = ? AND p.penalty_status = 'pending'";
$stmt = $db->prepare($penalties_sum_query);
$stmt->execute([$_SESSION['customer_id']]);
$penalties_sum = $stmt->fetch(PDO::FETCH_ASSOC);

// Pobierz listę kar klienta
$penalties_list_query = "SELECT p.*, r.rental_date, v.license_plate, vm.brand, vm.model 
                        FROM Penalty p 
                        JOIN Rental r ON p.rental_id = r.rental_id 
                        JOIN Vehicle v ON r.vehicle_id = v.vehicle_id 
                        JOIN VehicleModel vm ON v.model_id = vm.model_id 
                        WHERE r.customer_id = ? 
                        ORDER BY p.imposition_date DESC";
$stmt = $db->prepare($penalties_list_query);
$stmt->execute([$_SESSION['customer_id']]);
$penalties_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pobierz historię wypożyczeń
$history_query = "SELECT r.*, v.license_plate, vm.brand, vm.model 
                 FROM Rental r 
                 JOIN Vehicle v ON r.vehicle_id = v.vehicle_id 
                 JOIN VehicleModel vm ON v.model_id = vm.model_id 
                 WHERE r.customer_id = ? 
                 ORDER BY r.rental_date DESC";
$stmt = $db->prepare($history_query);
$stmt->execute([$_SESSION['customer_id']]);
$rental_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pobierz aktywne rezerwacje
$reservations_query = "SELECT res.*, v.license_plate, vm.brand, vm.model 
                      FROM Reservation res 
                      JOIN Vehicle v ON res.vehicle_id = v.vehicle_id 
                      JOIN VehicleModel vm ON v.model_id = vm.model_id 
                      WHERE res.customer_id = ? 
                      AND res.reservation_status IN ('pending', 'confirmed')
                      ORDER BY res.start_date ASC";
$stmt = $db->prepare($reservations_query);
$stmt->execute([$_SESSION['customer_id']]);
$active_reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pobierz aktywne wypożyczenia (do przedłużenia)
$active_rentals_query = "SELECT r.*, v.license_plate, vm.brand, vm.model 
                        FROM Rental r 
                        JOIN Vehicle v ON r.vehicle_id = v.vehicle_id 
                        JOIN VehicleModel vm ON v.model_id = vm.model_id 
                        WHERE r.customer_id = ? 
                        AND r.rental_status = 'active'
                        ORDER BY r.planned_return_date ASC";
$stmt = $db->prepare($active_rentals_query);
$stmt->execute([$_SESSION['customer_id']]);
$active_rentals = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>
<div class="dashboard">
    <h2>Mój Panel</h2>

    <?php if (isset($success)): ?>
        <div class="success"><?php echo $success; ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <div class="penalties-section">
        <h3>Moje Kary</h3>
        
        <div class="penalty-summary">
            <div class="info-card">
                <h4>Podsumowanie Kar</h4>
                <p class="penalty-amount">Suma kar do zapłaty: <strong><?php echo $penalties_sum['total_penalties'] ?? 0; ?> PLN</strong></p>
                <?php if ($penalties_sum['total_penalties'] > 0): ?>
                    <p class="penalty-warning">Prosimy o uregulowanie należności w terminie.</p>
                <?php endif; ?>
            </div>

            <?php if (!empty($penalties_list)): ?>
            <div class="penalties-list">
                <h4>Lista Kar</h4>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Data nałożenia</th>
                                <th>Pojazd</th>
                                <th>Kwota</th>
                                <th>Powód</th>
                                <th>Termin płatności</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($penalties_list as $penalty): ?>
                            <tr>
                                <td><?php echo $penalty['imposition_date']; ?></td>
                                <td>
                                    <?php echo $penalty['brand'] . ' ' . $penalty['model']; ?><br>
                                    <small><?php echo $penalty['license_plate']; ?></small>
                                </td>
                                <td><strong><?php echo $penalty['amount']; ?> PLN</strong></td>
                                <td><?php echo $penalty['reason']; ?></td>
                                <td>
                                    <?php echo $penalty['payment_deadline']; ?>
                                    <?php if (strtotime($penalty['payment_deadline']) < strtotime(date('Y-m-d')) && $penalty['penalty_status'] == 'pending'): ?>
                                        <span class="overdue-badge">PRZETERMINOWANA</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $penalty['penalty_status']; ?>">
                                        <?php 
                                        $status_text = [
                                            'pending' => 'Do zapłaty',
                                            'paid' => 'Opłacona',
                                            'cancelled' => 'Anulowana',
                                            'overdue' => 'Przeterminowana'
                                        ];
                                        echo $status_text[$penalty['penalty_status']] ?? $penalty['penalty_status'];
                                        ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php else: ?>
                <div class="no-penalties">
                    <p>Brak aktywnych kar.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="reservations-section">
        <h3>Moje Rezerwacje</h3>
        <?php if (empty($active_reservations)): ?>
            <p>Brak aktywnych rezerwacji</p>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Samochód</th>
                            <th>Data rozpoczęcia</th>
                            <th>Data zakończenia</th>
                            <th>Status</th>
                            <th>Akcje</th> </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($active_reservations as $reservation): ?>
                        <tr>
                            <td><?php echo $reservation['brand'] . ' ' . $reservation['model'] . ' (' . $reservation['license_plate'] . ')'; ?></td>
                            <td><?php echo $reservation['start_date']; ?></td>
                            <td><?php echo $reservation['end_date']; ?></td>
                            <td>
                                <span class="status-badge <?php echo $reservation['reservation_status']; ?>">
                                    <?php 
                                    $res_status_text = [
                                        'pending' => 'Oczekująca',
                                        'confirmed' => 'Potwierdzona',
                                        'cancelled' => 'Anulowana',
                                        'completed' => 'Zakończona'
                                    ];
                                    echo $res_status_text[$reservation['reservation_status']] ?? $reservation['reservation_status'];
                                    ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($reservation['reservation_status'] == 'pending'): ?>
                                    <button type="button" 
                                            class="btn btn-small btn-danger" 
                                            onclick="showCancelModal(<?php echo $reservation['reservation_id']; ?>, '<?php echo $reservation['brand'] . ' ' . $reservation['model']; ?>')">
                                        Anuluj
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="active-rentals-section">
        <h3>Aktywne Wypożyczenia</h3>
        <?php if (empty($active_rentals)): ?>
            <p>Brak aktywnych wypożyczeń</p>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Samochód</th>
                            <th>Data wypożyczenia</th>
                            <th>Planowany zwrot</th>
                            <th>Akcje</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($active_rentals as $rental): ?>
                        <tr>
                            <td><?php echo $rental['brand'] . ' ' . $rental['model'] . ' (' . $rental['license_plate'] . ')'; ?></td>
                            <td><?php echo $rental['rental_date']; ?></td>
                            <td><?php echo $rental['planned_return_date']; ?></td>
                            <td>
                                <button onclick="showExtendForm(<?php echo $rental['rental_id']; ?>, '<?php echo $rental['planned_return_date']; ?>')" 
                                        class="btn btn-small">
                                    Przedłuż
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div id="cancelReservationModal" class="form-modal" style="display: none;">
        <div class="modal-content">
            <h3>Anulowanie rezerwacji</h3>
            <p>Czy na pewno chcesz anulować rezerwację samochodu:</p>
            <p id="cancelModalVehicleName" style="font-weight: bold; margin: 10px 0; color: #e74c3c;"></p>
            
            <form method="POST">
                <input type="hidden" name="reservation_id" id="cancel_reservation_id">
                <input type="hidden" name="cancel_reservation" value="1">
                <div class="form-buttons" style="justify-content: center;">
                    <button type="submit" class="btn btn-danger">Tak, anuluj</button>
                    <button type="button" onclick="hideCancelModal()" class="btn btn-secondary">Wróć</button>
                </div>
            </form>
        </div>
    </div>

    <div id="extendForm" class="form-modal" style="display: none;">
        <div class="modal-content">
            <h3>Przedłuż Wypożyczenie</h3>
            <form method="POST">
                <input type="hidden" name="extend_rental_id_val" id="extend_rental_id">
                <div class="form-group">
                    <label>Obecna data zwrotu:</label>
                    <input type="text" id="current_return_date" readonly style="background: #f0f0f0;">
                </div>
                <div class="form-group">
                    <label>Nowa data zwrotu:</label>
                    <input type="date" name="new_return_date" id="new_return_date" required>
                </div>
                <div class="form-buttons">
                    <button type="submit" name="extend_rental" class="btn btn-success">Przedłuż</button>
                    <button type="button" onclick="hideExtendForm()" class="btn btn-secondary">Anuluj</button>
                </div>
            </form>
        </div>
    </div>

    <div class="history-section">
        <h3>Historia Wypożyczeń</h3>
        <?php if (empty($rental_history)): ?>
            <p>Brak historii wypożyczeń</p>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Samochód</th>
                            <th>Data wypożyczenia</th>
                            <th>Planowany zwrot</th>
                            <th>Rzeczywisty zwrot</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rental_history as $rental): ?>
                        <tr>
                            <td><?php echo $rental['brand'] . ' ' . $rental['model'] . ' (' . $rental['license_plate'] . ')'; ?></td>
                            <td><?php echo $rental['rental_date']; ?></td>
                            <td><?php echo $rental['planned_return_date']; ?></td>
                            <td><?php echo $rental['actual_return_date'] ?? 'Brak'; ?></td>
                            <td>
                                <span class="status-badge <?php echo $rental['rental_status']; ?>">
                                    <?php 
                                    $rental_status_text = [
                                        'active' => 'Aktywne',
                                        'completed' => 'Zakończone',
                                        'cancelled' => 'Anulowane',
                                        'overdue' => 'Przeterminowane'
                                    ];
                                    echo $rental_status_text[$rental['rental_status']] ?? $rental['rental_status'];
                                    ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Funkcje dla przedłużania wypożyczenia
function showExtendForm(rentalId, currentReturnDate) {
    document.getElementById('extend_rental_id').value = rentalId;
    document.getElementById('current_return_date').value = currentReturnDate;
    
    // Ustaw minimalną datę na dzień po obecnej dacie zwrotu
    const currentDate = new Date(currentReturnDate);
    currentDate.setDate(currentDate.getDate() + 1);
    const minDate = currentDate.toISOString().split('T')[0];
    
    document.getElementById('new_return_date').min = minDate;
    document.getElementById('new_return_date').value = minDate;
    
    document.getElementById('extendForm').style.display = 'flex'; // Zmieniono na flex dla centrowania
}

function hideExtendForm() {
    document.getElementById('extendForm').style.display = 'none';
}


function showCancelModal(reservationId, vehicleName) {
    document.getElementById('cancel_reservation_id').value = reservationId;
    document.getElementById('cancelModalVehicleName').textContent = vehicleName;
    document.getElementById('cancelReservationModal').style.display = 'flex';
}

function hideCancelModal() {
    document.getElementById('cancelReservationModal').style.display = 'none';
}

// Zamknij modale po kliknięciu poza nimi
window.onclick = function(event) {
    const extendModal = document.getElementById('extendForm');
    const cancelModal = document.getElementById('cancelReservationModal');
    
    if (event.target === extendModal) {
        hideExtendForm();
    }
    if (event.target === cancelModal) {
        hideCancelModal();
    }
}
</script>

<style>
/* Istniejące style zachowane... */
.penalties-section { margin: 2rem 0; padding: 1.5rem; background: #f8f9fa; border-radius: 8px; }
.penalties-section h3 { margin-bottom: 1.5rem; color: #2c3e50; }
.penalty-summary { background: white; padding: 1.5rem; border-radius: 8px; border: 1px solid #e0e0e0; }
.info-card { background: #fff8e1; padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem; border-left: 4px solid #ff9800; }
.info-card h4 { margin-bottom: 0.5rem; color: #333; }
.penalty-amount { font-size: 1.3rem; color: #e74c3c; font-weight: bold; margin: 0.5rem 0; }
.penalty-warning { color: #e67e22; font-style: italic; margin: 0.5rem 0 0 0; }
.penalties-list h4 { margin: 1.5rem 0 1rem 0; color: #333; }
.no-penalties { text-align: center; padding: 2rem; color: #666; }
.overdue-badge { display: inline-block; background: #e74c3c; color: white; padding: 0.2rem 0.5rem; border-radius: 3px; font-size: 0.7rem; font-weight: bold; margin-left: 0.5rem; }
.status-badge { padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.8rem; font-weight: bold; text-transform: uppercase; }
.status-badge.pending { background: #fff3cd; color: #856404; }
.status-badge.paid { background: #d4edda; color: #155724; }
.status-badge.cancelled { background: #e2e3e5; color: #383d41; }
.status-badge.overdue { background: #f8d7da; color: #721c24; }
.reservations-section, .active-rentals-section, .history-section { margin: 2rem 0; padding: 1.5rem; background: #f8f9fa; border-radius: 8px; }
.reservations-section h3, .active-rentals-section h3, .history-section h3 { margin-bottom: 1rem; color: #2c3e50; }
.status-badge.confirmed { background: #d4edda; color: #155724; }
.status-badge.active { background: #d1ecf1; color: #0c5460; }
.status-badge.completed { background: #d4edda; color: #155724; }

/* Style modala */
.form-modal { position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); display: flex; justify-content: center; align-items: center; }
.modal-content { background: white; padding: 2rem; border-radius: 8px; width: 90%; max-width: 400px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
.form-buttons { display: flex; gap: 1rem; margin-top: 1rem; }

.table-container { overflow-x: auto; margin: 1rem 0; }
table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; }
th, td { padding: 1rem; text-align: left; border-bottom: 1px solid #eee; }
th { background: #34495e; color: white; font-weight: bold; }
tr:hover { background: #f8f9fa; }
small { color: #666; font-size: 0.8rem; }
.btn-small { padding: 0.5rem 1rem; font-size: 0.9rem; }
.btn-danger { background-color: #e74c3c; color: white; border: none; cursor: pointer; }
.btn-danger:hover { background-color: #c0392b; }
.btn-success { background-color: #27ae60; color: white; border: none; }
.btn-secondary { background-color: #95a5a6; color: white; border: none; }

@media (max-width: 768px) {
    .table-container { font-size: 0.9rem; }
    th, td { padding: 0.75rem 0.5rem; }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>