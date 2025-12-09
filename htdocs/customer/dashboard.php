<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
redirectIfNotCustomer();

$database = new Database();
$db = $database->getConnection();

// Przedłużenie wypożyczenia
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['extend_rental'])) {
    $rental_id = $_POST['rental_id'];
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

// Pobierz kary klienta
$penalties_query = "SELECT SUM(amount) as total_penalties FROM Penalty p 
                   JOIN Rental r ON p.rental_id = r.rental_id 
                   WHERE r.customer_id = ? AND p.penalty_status = 'pending'";
$stmt = $db->prepare($penalties_query);
$stmt->execute([$_SESSION['customer_id']]);
$penalties = $stmt->fetch(PDO::FETCH_ASSOC);

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
    
    <div class="info-card">
        <h3>Podsumowanie Kar</h3>
        <p class="penalty-amount">Suma kar do zapłaty: <strong><?php echo $penalties['total_penalties'] ?? 0; ?> PLN</strong></p>
    </div>

    <!-- Aktywne rezerwacje -->
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
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($active_reservations as $reservation): ?>
                        <tr>
                            <td><?php echo $reservation['brand'] . ' ' . $reservation['model'] . ' (' . $reservation['license_plate'] . ')'; ?></td>
                            <td><?php echo $reservation['start_date']; ?></td>
                            <td><?php echo $reservation['end_date']; ?></td>
                            <td>
                                <span class="status-badge <?php echo $reservation['reservation_status']; ?>">
                                    <?php echo $reservation['reservation_status']; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Aktywne wypożyczenia z możliwością przedłużenia -->
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

    <!-- Formularz przedłużenia wypożyczenia -->
    <div id="extendForm" class="form-modal" style="display: none;">
        <div class="modal-content">
            <h3>Przedłuż Wypożyczenie</h3>
            <form method="POST">
                <input type="hidden" name="rental_id" id="extend_rental_id">
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

    <!-- Historia wypożyczeń -->
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
                                    <?php echo $rental['rental_status']; ?>
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
function showExtendForm(rentalId, currentReturnDate) {
    document.getElementById('extend_rental_id').value = rentalId;
    document.getElementById('current_return_date').value = currentReturnDate;
    
    // Ustaw minimalną datę na dzień po obecnej dacie zwrotu
    const currentDate = new Date(currentReturnDate);
    currentDate.setDate(currentDate.getDate() + 1);
    const minDate = currentDate.toISOString().split('T')[0];
    
    document.getElementById('new_return_date').min = minDate;
    document.getElementById('new_return_date').value = minDate;
    
    document.getElementById('extendForm').style.display = 'block';
}

function hideExtendForm() {
    document.getElementById('extendForm').style.display = 'none';
}

// Zamknij modal po kliknięciu poza nim
window.onclick = function(event) {
    const modal = document.getElementById('extendForm');
    if (event.target === modal) {
        hideExtendForm();
    }
}
</script>

<style>
.reservations-section,
.active-rentals-section,
.history-section {
    margin: 2rem 0;
    padding: 1.5rem;
    background: #f8f9fa;
    border-radius: 8px;
}

.reservations-section h3,
.active-rentals-section h3,
.history-section h3 {
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
    max-width: 400px;
}

.form-buttons {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>