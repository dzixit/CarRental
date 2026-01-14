<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
redirectIfNotCustomer();

$database = new Database();
$db = $database->getConnection();

$pricing = [
    'sedan' => 100, 'suv' => 150, 'hatchback' => 80, 'coupe' => 120,
    'convertible' => 200, 'van' => 180, 'truck' => 220
];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['make_reservation'])) {
    $vehicle_id = $_POST['vehicle_id'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    
    $query = "INSERT INTO Reservation (start_date, end_date, customer_id, vehicle_id, reservation_status) 
              VALUES (?, ?, ?, ?, 'pending')";
    $stmt = $db->prepare($query);
    if ($stmt->execute([$start_date, $end_date, $_SESSION['customer_id'], $vehicle_id])) {
        
        // Wysyłanie maila potwierdzającego
        require_once __DIR__ . '/../includes/email_sender.php';
        
        // Pobierz dane klienta
        $stmt_user = $db->prepare("SELECT email, first_name FROM Customer WHERE customer_id = ?");
        $stmt_user->execute([$_SESSION['customer_id']]);
        $user = $stmt_user->fetch(PDO::FETCH_ASSOC);
        
        // Pobierz dane pojazdu
        $stmt_vehicle = $db->prepare("SELECT brand, model FROM VehicleModel vm JOIN Vehicle v ON v.model_id = vm.model_id WHERE v.vehicle_id = ?");
        $stmt_vehicle->execute([$vehicle_id]);
        $veh = $stmt_vehicle->fetch(PDO::FETCH_ASSOC);
        
        if ($user && $veh) {
            sendReservationConfirmationEmail(
                $user['email'], 
                $user['first_name'], 
                $veh['brand'] . ' ' . $veh['model'], 
                $start_date, 
                $end_date
            );
        }
        
        $success = "Rezerwacja została złożona! Sprawdź skrzynkę mailową.";
    } else {
        $error = "Błąd podczas składania rezerwacji!";
    }
}

$available_vehicles = [];
$total_days = 0;
$start_date_input = '';
$end_date_input = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['check_availability'])) {
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $start_date_input = $start_date;
    $end_date_input = $end_date;
    
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $total_days = $start->diff($end)->days + 1;
    
    $query = "SELECT v.*, vm.brand, vm.model, vm.vehicle_type 
              FROM Vehicle v 
              JOIN VehicleModel vm ON v.model_id = vm.model_id 
              WHERE v.status = 'available' 
              AND v.vehicle_id NOT IN (
                  SELECT vehicle_id FROM Rental 
                  WHERE rental_status = 'active' 
                  AND (rental_date BETWEEN ? AND ? OR planned_return_date BETWEEN ? AND ?)
              )";
    $stmt = $db->prepare($query);
    $stmt->execute([$start_date, $end_date, $start_date, $end_date]);
    $available_vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($available_vehicles as &$vehicle) {
        $daily_price = $pricing[$vehicle['vehicle_type']] ?? 100;
        $vehicle['daily_price'] = $daily_price;
        $vehicle['total_cost'] = $daily_price * $total_days;
    }
    unset($vehicle);
}
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>
<div class="dashboard">
    <h2>Rezerwacje</h2>

    <div class="reservation-form">
        <h3>Sprawdź dostępność samochodów</h3>
        <form method="POST" id="reservationForm">
            <div class="form-row">
                <div class="form-group">
                    <label>Data rozpoczęcia:</label>
                    <input type="date" name="start_date" id="start_date" required min="<?php echo date('Y-m-d'); ?>" value="<?php echo $start_date_input; ?>">
                </div>
                <div class="form-group">
                    <label>Data zakończenia:</label>
                    <input type="date" name="end_date" id="end_date" required min="<?php echo date('Y-m-d'); ?>" value="<?php echo $end_date_input; ?>">
                </div>
            </div>
            <?php if ($total_days > 0): ?>
            <div class="reservation-summary">
                <p><strong>Okres wynajmu:</strong> <?php echo $total_days; ?> dni</p>
                <p><strong>Od:</strong> <?php echo date('d.m.Y', strtotime($start_date_input)); ?> 
                <strong>Do:</strong> <?php echo date('d.m.Y', strtotime($end_date_input)); ?></p>
            </div>
            <?php endif; ?>
            <button type="submit" name="check_availability" class="btn">Sprawdź dostępność</button>
        </form>
    </div>

    <?php if (isset($success)): ?>
        <div class="success"><?php echo $success; ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="error"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if (!empty($available_vehicles)): ?>
    <div class="available-vehicles">
        <h3>Dostępne samochody (<?php echo $total_days; ?> dni)</h3>
        <div class="vehicles-grid">
            <?php foreach ($available_vehicles as $vehicle): ?>
            <div class="vehicle-card">
                <div class="vehicle-image">
                    <img src="https://via.placeholder.com/300x200/2c3e50/ffffff?text=<?php echo urlencode($vehicle['brand'].'+'.$vehicle['model']); ?>" 
                         alt="<?php echo $vehicle['brand'] . ' ' . $vehicle['model']; ?>">
                </div>
                <h4><?php echo $vehicle['brand'] . ' ' . $vehicle['model']; ?></h4>
                <div class="vehicle-details">
                    <p><strong>Typ:</strong> <?php echo $vehicle['vehicle_type']; ?></p>
                    <p><strong>Rejestracja:</strong> <?php echo $vehicle['license_plate']; ?></p>
                    <p><strong>Koszt całkowity:</strong> <span style="color:#e74c3c;font-weight:bold;"><?php echo $vehicle['total_cost']; ?> PLN</span></p>
                </div>
                
                <form method="POST" class="reservation-form-inline">
                    <input type="hidden" name="vehicle_id" value="<?php echo $vehicle['vehicle_id']; ?>">
                    <input type="hidden" name="start_date" value="<?php echo $start_date_input; ?>">
                    <input type="hidden" name="end_date" value="<?php echo $end_date_input; ?>">
                    <button type="submit" name="make_reservation" class="btn">Zarezerwuj</button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['check_availability'])): ?>
        <div class="info">Brak dostępnych samochodów w wybranym terminie.</div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    
    function calculateDays() {
        const start = new Date(startDateInput.value);
        const end = new Date(endDateInput.value);
        if (start && end && start <= end) {
            const diffTime = Math.abs(end - start);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
            const summaryElement = document.querySelector('.reservation-summary');
            if (summaryElement && startDateInput.value && endDateInput.value) {
                summaryElement.innerHTML = `
                    <p><strong>Okres wynajmu:</strong> ${diffDays} dni</p>
                    <p><strong>Od:</strong> ${startDateInput.value} <strong>Do:</strong> ${endDateInput.value}</p>
                `;
            }
        }
    }
    startDateInput.addEventListener('change', calculateDays);
    endDateInput.addEventListener('change', calculateDays);
});
</script>

<style>

.reservation-summary { background: #e8f5e8; padding: 1rem; border-radius: 5px; margin: 1rem 0; border-left: 4px solid #27ae60; }
.vehicle-card { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border: 1px solid #e0e0e0; margin-bottom: 20px;}
.vehicles-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem; margin: 2rem 0; }
.vehicle-image img { width: 100%; height: 200px; object-fit: cover; border-radius: 8px; }
.reservation-form-inline .btn { width: 100%; background: #27ae60; margin-top: 1rem; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>