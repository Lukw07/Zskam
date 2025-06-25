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

    <div class="container mt-4 fade-in" id="dashboard-content">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show fade-in" role="alert">
                <?= $_SESSION['success'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show fade-in" role="alert">
                <?= $_SESSION['error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="dashboard-stats fade-in">
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
        <div class="card mb-4 fade-in">
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
                        $now = new DateTime();
                        $next_hour_found = false;
                        foreach ($grouped as $group) {
                            $rows = $group['rows'];
                            $reserved_hours = array_map(function($r) { return $r['hour']; }, $rows);
                            sort($reserved_hours);
                            $is_all_day = ($reserved_hours === $all_hours);
                            if ($is_all_day) {
                                $first = $rows[0];
                                $last = $rows[count($rows)-1];
                                echo '<tr class="all-day-row">';
                                echo '<td>' . htmlspecialchars($group['device_name']) . '</td>';
                                echo '<td>' . date('d.m.Y', strtotime($group['date'])) . '</td>';
                                echo '<td colspan="2"><span class="badge badge-all-day">celý den</span></td>';
                                echo '<td>' . $group['quantity'] . '</td>';
                                echo '<td>' . htmlspecialchars($group['user_name']) . '</td>';
                                echo '<td>';
                                if (is_admin() || $group['user_id'] == $_SESSION['user_id']) {
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
                                foreach ($rows as $row) {
                                    $row_time = new DateTime($row['date'] . ' ' . $row['start_time']);
                                    $is_next = false;
                                    if (!$next_hour_found && $row_time > $now) {
                                        $is_next = true;
                                        $next_hour_found = true;
                                    }
                                    $row_class = $is_next ? 'next-hour-row' : '';
                                    echo '<tr class="' . $row_class . '">';
                                    echo '<td>' . htmlspecialchars($row['device_name']) . '</td>';
                                    echo '<td>' . date('d.m.Y', strtotime($row['date'])) . '</td>';
                                    echo '<td>' . $row['hour'] . '. hodina';
                                    if ($is_next) echo ' <span class="badge badge-next-hour ms-2">nadcházející hodina</span>';
                                    echo '</td>';
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
        <div class="card mb-4 fade-in">
            <div class="card-body">
                <h2 class="card-title">Technické problémy</h2>
                <?php
                // Načtení prvních 10 technických problémů
                $issues_limit = 10;
                if (isset($_GET['issues_offset'])) {
                    $issues_offset = intval($_GET['issues_offset']);
                } else {
                    $issues_offset = 0;
                }
                $query = "SELECT ti.*, u.name as user_name 
                         FROM technical_issues ti
                         JOIN users u ON ti.user_id = u.id
                         WHERE ti.status != 'vyřešeno'
                         ORDER BY ti.created_at DESC";
                $issues = $conn->query($query);
                // Zjisti, zda existují další záznamy
                $next_issues = $conn->query("SELECT COUNT(*) as cnt FROM technical_issues WHERE status != 'vyřešeno'");
                $issues_total = $next_issues->fetch_assoc()['cnt'];
                $has_more_issues = ($issues_offset + $issues_limit) < $issues_total;
                ?>
                <div class="table-responsive">
                    <table class="table table-striped" id="issues-table">
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
                        <tbody id="issues-tbody">
                            <?php while ($issue = $issues->fetch_assoc()): ?>
                            <tr class="fade-in-row issue-row issue-priority-<?= $issue['urgency'] ?>">
                                <td><?= htmlspecialchars($issue['class']) ?></td>
                                <td><?= htmlspecialchars($issue['description']) ?></td>
                                <td>
                                    <span class="badge badge-priority badge-priority-<?= $issue['urgency'] ?>" data-bs-toggle="tooltip" title="Priorita: <?= ucfirst($issue['urgency']) ?>">
                                        <?php if ($issue['urgency'] === 'vysoká'): ?>
                                            <i class="fas fa-arrow-up"></i>
                                        <?php elseif ($issue['urgency'] === 'střední'): ?>
                                            <i class="fas fa-arrow-right"></i>
                                        <?php else: ?>
                                            <i class="fas fa-arrow-down"></i>
                                        <?php endif; ?>
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
        // Scroll reveal animace sekcí
        function revealOnScroll() {
            document.querySelectorAll('.fade-in').forEach(el => {
                const rect = el.getBoundingClientRect();
                if (rect.top < window.innerHeight - 60) {
                    el.classList.add('visible');
                }
            });
        }
        document.addEventListener('scroll', revealOnScroll);
        document.addEventListener('DOMContentLoaded', revealOnScroll);
    </script>
    <style>
    .fade-in-row {
        animation: fadeInUp 0.7s cubic-bezier(0.4,0,0.2,1);
    }
    .skeleton-loader, .skeleton-row {
        width: 100%;
        height: 32px;
        background: linear-gradient(90deg, #f0f2f5 25%, #eaf5fc 50%, #f0f2f5 75%);
        background-size: 200% 100%;
        animation: skeleton 1.2s infinite linear;
        border-radius: 8px;
    }
    .skeleton-row td {
        background: none;
        padding: 0;
    }
    @keyframes skeleton {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }
    .table tbody tr {
        transition: background 0.25s, box-shadow 0.25s, transform 0.18s;
        cursor: pointer;
    }
    .table tbody tr:hover {
        background: #eaf5fc;
        box-shadow: 0 2px 8px rgba(52,152,219,0.10);
        transform: scale(1.012);
    }
    .card, .card-body {
        transition: box-shadow 0.3s, transform 0.3s;
    }
    .card:hover {
        box-shadow: 0 10px 32px rgba(52,152,219,0.13), 0 2px 8px rgba(52,152,219,0.08);
        transform: translateY(-2px) scale(1.01);
    }
    .badge, .animated-icon {
        transition: transform 0.3s cubic-bezier(0.4,0,0.2,1), box-shadow 0.2s;
    }
    .badge:hover, .animated-icon:hover {
        transform: scale(1.18) rotate(-8deg);
        box-shadow: 0 2px 8px rgba(52,152,219,0.10);
    }
    </style>
    <?php include 'footer.php'; ?>
</body>
</html>