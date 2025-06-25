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
    <title>Rezervo - Historie</title>
    <link rel="icon" type="image/png" href="logo1.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4" id="history-content">
        <h2>Historie rezervací</h2>
        
        <form method="get" class="row g-3 mb-4" id="filterForm">
            <div class="col-md-3">
                <label class="form-label">Zařízení:</label>
                <select name="device_id" class="form-select" onchange="this.form.submit()">
                    <option value="">Všechna zařízení</option>
                    <?php
                    $devices = $conn->query("SELECT id, device_name FROM devices ORDER BY device_name");
                    $selected_device = isset($_GET['device_id']) ? $_GET['device_id'] : '';
                    while ($dev = $devices->fetch_assoc()): ?>
                        <option value="<?= $dev['id'] ?>" <?= $selected_device !== '' && $selected_device == $dev['id'] ? 'selected' : '' ?>><?= htmlspecialchars($dev['device_name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Datum vytvoření:</label>
                <input type="date" name="date" class="form-control" value="<?= isset($_GET['date']) ? htmlspecialchars($_GET['date']) : '' ?>" onchange="this.form.submit()">
            </div>
            <?php if (is_admin()): ?>
            <div class="col-md-3">
                <label class="form-label">Uživatel:</label>
                <select name="user_id" class="form-select" onchange="this.form.submit()">
                    <option value="">Všichni uživatelé</option>
                    <?php
                    $users = $conn->query("SELECT id, name FROM users ORDER BY name");
                    $selected_user = isset($_GET['user_id']) ? $_GET['user_id'] : '';
                    while ($u = $users->fetch_assoc()): ?>
                        <option value="<?= $u['id'] ?>" <?= $selected_user !== '' && $selected_user == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-12">
                <a href="history.php" class="btn btn-secondary">Zrušit filtry</a>
            </div>
        </form>
        
        <?php
        // Sestavení dotazu s filtry
        $query = "SELECT r.*, d.device_name, u.name as user_name 
                 FROM reservations r
                 JOIN devices d ON r.device_id = d.id
                 JOIN users u ON r.user_id = u.id
                 WHERE (r.date < CURDATE() OR 
                 (r.date = CURDATE() AND (SELECT end_time FROM hours WHERE hour_number = r.hour) < CURTIME()))";

        if (!empty($_GET['device_id'])) {
            $query .= " AND r.device_id = " . intval($_GET['device_id']);
        }
        if (!empty($_GET['date'])) {
            $query .= " AND DATE(r.timestamp_created) = '" . $conn->real_escape_string($_GET['date']) . "'";
        }
        if (is_admin() && !empty($_GET['user_id'])) {
            $query .= " AND r.user_id = " . intval($_GET['user_id']);
        }
        if (!is_admin()) {
            $query .= " AND r.user_id = " . $_SESSION['user_id'];
        }
        $query .= " ORDER BY r.date DESC, r.hour DESC";
        $reservations = $conn->query($query);

        // Seskupení rezervací na celý den (stejně jako v dashboardu)
        $grouped = [];
        $all_hours = [];
        $hours_res = $conn->query("SELECT hour_number FROM hours ORDER BY hour_number");
        while ($h = $hours_res->fetch_assoc()) {
            $all_hours[] = $h['hour_number'];
        }
        while ($row = $reservations->fetch_assoc()) {
            $key = $row['user_id'] . '|' . $row['device_id'] . '|' . $row['date'] . '|' . $row['quantity'];
            $grouped[$key]['rows'][] = $row;
            $grouped[$key]['device_name'] = $row['device_name'];
            $grouped[$key]['date'] = $row['date'];
            $grouped[$key]['user_name'] = $row['user_name'];
            $grouped[$key]['user_id'] = $row['user_id'];
            $grouped[$key]['quantity'] = $row['quantity'];
        }
        ?>
        
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Zařízení</th>
                    <th>Datum</th>
                    <th>Hodina</th>
                    <th>Počet</th>
                    <th>Rezervoval</th>
                    <th>Vytvořeno</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($grouped as $group) {
                    $rows = $group['rows'];
                    $reserved_hours = array_map(function($r) { return $r['hour']; }, $rows);
                    sort($reserved_hours);
                    $is_all_day = ($reserved_hours === $all_hours);
                    if ($is_all_day) {
                        $first = $rows[0];
                        $last = $rows[count($rows)-1];
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($group['device_name']) . '</td>';
                        echo '<td>' . date('d.m.Y', strtotime($group['date'])) . '</td>';
                        echo '<td colspan="2"><span class="badge bg-primary">celý den</span></td>';
                        echo '<td>' . $group['quantity'] . '</td>';
                        echo '<td>' . htmlspecialchars($group['user_name']) . '</td>';
                        echo '<td>' . date('d.m.Y H:i', strtotime($first['timestamp_created'])) . '</td>';
                        echo '</tr>';
                    } else {
                        foreach ($rows as $row) {
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars($row['device_name']) . '</td>';
                            echo '<td>' . date('d.m.Y', strtotime($row['date'])) . '</td>';
                            echo '<td>' . $row['hour'] . '. hodina</td>';
                            echo '<td>' . $row['quantity'] . '</td>';
                            echo '<td>' . htmlspecialchars($row['user_name']) . '</td>';
                            echo '<td>' . date('d.m.Y H:i', strtotime($row['timestamp_created'])) . '</td>';
                            echo '</tr>';
                        }
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'footer.php'; ?>
</body>
</html>