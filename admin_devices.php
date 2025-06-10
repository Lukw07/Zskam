<?php
require_once 'auth.php';
require_once 'db.php';
redirect_if_not_logged_in();
if (!is_admin()) die("Přístup odepřen");

// Správa zařízení
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'create':
            $device_name = $conn->real_escape_string($_POST['device_name']);
            $total_quantity = intval($_POST['total_quantity']);
            
            $stmt = $conn->prepare("INSERT INTO devices (device_name, total_quantity) VALUES (?, ?)");
            $stmt->bind_param("si", $device_name, $total_quantity);
            $stmt->execute();
            break;
            
        case 'delete':
            $id = intval($_POST['id']);
            $conn->query("DELETE FROM devices WHERE id = $id");
            break;
            
        case 'update':
            $id = intval($_POST['id']);
            $device_name = $conn->real_escape_string($_POST['device_name']);
            $total_quantity = intval($_POST['total_quantity']);
            
            $stmt = $conn->prepare("UPDATE devices SET device_name = ?, total_quantity = ? WHERE id = ?");
            $stmt->bind_param("sii", $device_name, $total_quantity, $id);
            $stmt->execute();
            break;
    }
}

$devices = $conn->query("SELECT * FROM devices");
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rezervo - Správa zařízení</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4" id="devices-content">
        <h2>Správa zařízení</h2>
        
        <div class="mb-4">
            <h4>Nové zařízení</h4>
            <form method="post">
                <input type="hidden" name="action" value="create">
                <div class="row g-3">
                    <div class="col-md-6">
                        <input type="text" name="device_name" class="form-control" placeholder="Název zařízení" required>
                    </div>
                    <div class="col-md-4">
                        <input type="number" name="total_quantity" class="form-control" placeholder="Celkový počet kusů" min="1" required>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-success">Přidat</button>
                    </div>
                </div>
            </form>
        </div>

        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Název zařízení</th>
                    <th>Celkový počet</th>
                    <th>Akce</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($device = $devices->fetch_assoc()): ?>
                <tr>
                    <td><?= $device['id'] ?></td>
                    <td>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="id" value="<?= $device['id'] ?>">
                            <div class="input-group">
                                <input type="text" name="device_name" class="form-control" value="<?= htmlspecialchars($device['device_name']) ?>">
                                <input type="number" name="total_quantity" class="form-control" value="<?= $device['total_quantity'] ?>" min="1">
                                <button type="submit" class="btn btn-primary btn-sm">Upravit</button>
                            </div>
                        </form>
                    </td>
                    <td><?= $device['total_quantity'] ?></td>
                    <td>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $device['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Opravdu chcete smazat toto zařízení?')">Smazat</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="auto_refresh.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            setupAutoRefresh('devices-content', 'admin_devices.php', 60000);
        });
    </script>
</body>
</html> 