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
    <title>Rezervo - Dashboard</title>
    <link rel="icon" type="image/png" href="logo1.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4" id="dashboard-content">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $_SESSION['success'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $_SESSION['error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="dashboard-stats">
            <?php
            // Počet aktivních rezervací uživatele
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM reservations r
                                  JOIN hours h ON r.hour = h.hour_number
                                  WHERE r.user_id = ? AND (r.date > CURDATE() OR 
                                  (r.date = CURDATE() AND h.end_time > CURTIME()))");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $active_count = $stmt->get_result()->fetch_assoc()['count'];
            ?>
            <div class="stat-card">
                <h3><?= $active_count ?></h3>
                <p>Aktivní rezervace</p>
            </div>

            <?php
            // Celkový počet zařízení
            $total_devices = $conn->query("SELECT COUNT(*) as count FROM devices")->fetch_assoc()['count'];
            ?>
            <div class="stat-card">
                <h3><?= $total_devices ?></h3>
                <p>Dostupná zařízení</p>
            </div>

            <?php if (is_admin()): ?>
            <?php
            // Počet nových technických problémů
            $new_issues = $conn->query("SELECT COUNT(*) as count FROM technical_issues WHERE status = 'nový'")->fetch_assoc()['count'];
            ?>
            <div class="stat-card">
                <h3><?= $new_issues ?></h3>
                <p>Nové technické problémy</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Aktivní rezervace -->
        <div class="card mb-4">
            <div class="card-body">
                <h2 class="card-title">Aktivní rezervace</h2>
                <?php
                $query = "SELECT r.*, d.device_name, h.start_time, h.end_time, u.name as user_name 
                         FROM reservations r
                         JOIN devices d ON r.device_id = d.id
                         JOIN hours h ON r.hour = h.hour_number
                         JOIN users u ON r.user_id = u.id
                         WHERE (r.date > CURDATE() OR 
                         (r.date = CURDATE() AND h.end_time > CURTIME()))
                         ORDER BY 
                            CASE 
                                WHEN r.date = CURDATE() THEN 1
                                WHEN r.date = DATE_ADD(CURDATE(), INTERVAL 1 DAY) THEN 2
                                ELSE 3
                            END,
                            r.date ASC,
                            r.hour ASC,
                            d.device_name ASC";
                
                $reservations = $conn->query($query);
                // Seskupení rezervací na celý den
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
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Zařízení</th>
                                <th>Datum</th>
                                <th>Hodina</th>
                                <th>Čas</th>
                                <th>Počet</th>
                                <th>Rezervoval</th>
                                <th>Akce</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        foreach ($grouped as $group) {
                            $rows = $group['rows'];
                            // Zjisti, jestli je to rezervace na celý den
                            $reserved_hours = array_map(function($r) { return $r['hour']; }, $rows);
                            sort($reserved_hours);
                            $is_all_day = ($reserved_hours === $all_hours);
                            if ($is_all_day) {
                                // Sloučený řádek pro celý den
                                $first = $rows[0];
                                $last = $rows[count($rows)-1];
                                echo '<tr>';
                                echo '<td>' . htmlspecialchars($group['device_name']) . '</td>';
                                echo '<td>' . date('d.m.Y', strtotime($group['date'])) . '</td>';
                                echo '<td colspan="2"><span class="badge bg-primary">celý den</span></td>';
                                echo '<td>' . $group['quantity'] . '</td>';
                                echo '<td>' . htmlspecialchars($group['user_name']) . '</td>';
                                echo '<td>';
                                if (is_admin() || $group['user_id'] == $_SESSION['user_id']) {
                                    // Smazat všechny rezervace tohoto bloku
                                    echo '<form method="post" action="delete_reservation.php" class="d-inline">';
                                    foreach ($rows as $r) {
                                        echo '<input type="hidden" name="reservation_ids[]" value="' . $r['id'] . '">';
                                    }
                                    echo '<button type="submit" class="btn btn-danger btn-sm" onclick="return confirm(\'Opravdu chcete smazat tuto rezervaci na celý den?\')">';
                                    echo '<i class="fas fa-trash"></i> Smazat celý den';
                                    echo '</button>';
                                    echo '</form>';
                                }
                                echo '</td>';
                                echo '</tr>';
                            } else {
                                // Obyčejné řádky po hodinách
                                foreach ($rows as $row) {
                                    echo '<tr>';
                                    echo '<td>' . htmlspecialchars($row['device_name']) . '</td>';
                                    echo '<td>' . date('d.m.Y', strtotime($row['date'])) . '</td>';
                                    echo '<td>' . $row['hour'] . '. hodina</td>';
                                    echo '<td>' . date('H:i', strtotime($row['start_time'])) . ' - ' . date('H:i', strtotime($row['end_time'])) . '</td>';
                                    echo '<td>' . $row['quantity'] . '</td>';
                                    echo '<td>' . htmlspecialchars($row['user_name']) . '</td>';
                                    echo '<td>';
                                    if (is_admin() || $row['user_id'] == $_SESSION['user_id']) {
                                        echo '<form method="post" action="delete_reservation.php" class="d-inline">';
                                        echo '<input type="hidden" name="reservation_id" value="' . $row['id'] . '">';
                                        echo '<button type="submit" class="btn btn-danger btn-sm" onclick="return confirm(\'Opravdu chcete smazat tuto rezervaci?\')">';
                                        echo '<i class="fas fa-trash"></i> Smazat';
                                        echo '</button>';
                                        echo '</form>';
                                    }
                                    echo '</td>';
                                    echo '</tr>';
                                }
                            }
                        }
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Technické problémy -->
        <div class="card mb-4">
            <div class="card-body">
                <h2 class="card-title">Technické problémy</h2>
                <?php
                $query = "SELECT ti.*, u.name as user_name 
                         FROM technical_issues ti
                         JOIN users u ON ti.user_id = u.id
                         WHERE ti.status != 'vyřešeno'
                         ORDER BY 
                            CASE ti.urgency
                                WHEN 'vysoká' THEN 1
                                WHEN 'střední' THEN 2
                                ELSE 3
                            END,
                            ti.created_at DESC";
                
                $issues = $conn->query($query);
                ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Třída</th>
                                <th>Popis</th>
                                <th>Naléhavost</th>
                                <th>Stav</th>
                                <th>Nahlásil</th>
                                <th>Datum nahlášení</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($issue = $issues->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($issue['class']) ?></td>
                                <td><?= htmlspecialchars($issue['description']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $issue['urgency'] === 'vysoká' ? 'danger' : ($issue['urgency'] === 'střední' ? 'warning' : 'info') ?>">
                                        <?= $issue['urgency'] ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $issue['status'] === 'nový' ? 'primary' : 'secondary' ?>">
                                        <?= $issue['status'] ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($issue['user_name']) ?></td>
                                <td><?= date('d.m.Y H:i', strtotime($issue['created_at'])) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="auto_refresh.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            setupAutoRefresh('dashboard-content', 'dashboard.php', 60000);
        });
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>