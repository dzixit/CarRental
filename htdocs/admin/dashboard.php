<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
redirectIfNotAdmin();

$database = new Database();
$db = $database->getConnection();

// Pobierz powiadomienia
$notifications_query = "SELECT v.*, vm.brand, vm.model, s.service_type 
                       FROM Vehicle v 
                       JOIN VehicleModel vm ON v.model_id = vm.model_id 
                       LEFT JOIN Service s ON v.vehicle_id = s.vehicle_id AND s.service_status != 'completed'
                       WHERE v.status = 'maintenance' OR s.service_status = 'scheduled'";
$notifications = $db->query($notifications_query)->fetchAll(PDO::FETCH_ASSOC);
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>
<div class="dashboard">
    <h2>Panel Administratora</h2>

    <div class="notifications-section">
        <h3>Powiadomienia</h3>
        <?php if (empty($notifications)): ?>
            <p>Brak powiadomień</p>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Samochód</th>
                            <th>Status</th>
                            <th>Typ serwisu</th>
                            <th>Akcje</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($notifications as $vehicle): ?>
                        <tr>
                            <td><?php echo $vehicle['brand'] . ' ' . $vehicle['model'] . ' (' . $vehicle['license_plate'] . ')'; ?></td>
                            <td><?php echo $vehicle['status']; ?></td>
                            <td><?php echo $vehicle['service_type'] ?? 'Brak'; ?></td>
                            <td>
                                <a href="vehicles.php?edit=<?php echo $vehicle['vehicle_id']; ?>" class="btn btn-small">Zarządzaj</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="quick-actions">
        <h3>Szybkie akcje</h3>
        <div class="action-buttons">
            <a href="customers.php" class="btn">Zarządzaj klientami</a>
            <a href="vehicles.php" class="btn">Zarządzaj samochodami</a>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>