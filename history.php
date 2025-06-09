<?php
require_once 'auth.php';
require_once 'db.php';
redirect_if_not_logged_in();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historie</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4" id="history-content">
        <h2>Historie rezervací</h2>
        
        <?php
        $query = "SELECT r.*, d.device_name, u.name as user_name 
                 FROM reservations r
                 JOIN devices d ON r.device_id = d.id
                 JOIN users u ON r.user_id = u.id
                 WHERE (r.date < CURDATE() OR 
                 (r.date = CURDATE() AND (SELECT end_time FROM hours WHERE hour_number = r.hour) < CURTIME()))";
        
        // Přidání podmínky pro běžné uživatele
        if (!is_admin()) {
            $query .= " AND r.user_id = " . $_SESSION['user_id'];
        }
        
        $query .= " ORDER BY r.date DESC, r.hour DESC";

        $reservations = $conn->query($query);
        ?>
        
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Zařízení</th>
                    <th>Datum</th>
                    <th>Hodina</th>
                    <th>Počet</th>
                    <th>Rezervoval</th>
                    <th>Čas vytvoření</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $reservations->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['device_name']) ?></td>
                    <td><?= date('d.m.Y', strtotime($row['date'])) ?></td>
                    <td><?= $row['hour'] ?>. hodina</td>
                    <td><?= $row['quantity'] ?></td>
                    <td><?= htmlspecialchars($row['user_name']) ?></td>
                    <td><?= date('d.m.Y H:i', strtotime($row['timestamp_created'])) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="auto_refresh.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            setupAutoRefresh('history-content', 'history.php', 60000);
        });
    </script>
</body>
</html>