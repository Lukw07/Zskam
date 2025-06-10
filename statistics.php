<?php
require_once 'auth.php';
require_once 'db.php';
redirect_if_not_logged_in();
if (!is_admin()) die("Přístup odepřen");
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rezervo - Statistiky</title>
    <link rel="icon" type="image/avif" href="https://zskamenicka.cz/wp-content/uploads/2025/06/ChatGPT-Image-9.-6.-2025-22_07_53.avif">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Responzivní styly */
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }

            .card {
                margin-bottom: 1rem;
            }

            .card-body {
                padding: 1rem;
            }

            /* Úprava nadpisů */
            h2 {
                font-size: 1.5rem;
                margin-bottom: 1rem;
            }

            h3 {
                font-size: 1.2rem;
                margin-bottom: 0.75rem;
            }

            /* Úprava statistik */
            .stat-card {
                padding: 1rem;
                margin-bottom: 1rem;
                text-align: center;
            }

            .stat-card h4 {
                font-size: 1.5rem;
                margin-bottom: 0.5rem;
            }

            .stat-card p {
                font-size: 0.9rem;
                margin-bottom: 0;
            }

            /* Úprava grafů */
            canvas {
                max-height: 250px;
            }

            /* Úprava řádků */
            .row {
                margin-bottom: 0.5rem;
            }

            .col-md-6 {
                margin-bottom: 1rem;
            }

            /* Úprava detailních statistik */
            .col-md-3 {
                margin-bottom: 1rem;
            }

            /* Úprava nadpisů grafů */
            .chart-title {
                font-size: 1rem;
                margin-bottom: 0.5rem;
            }

            /* Úprava legendy */
            .chart-legend {
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <h2>Statistiky</h2>

        <div class="row">
            <!-- Statistiky rezervací -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title">Statistiky rezervací</h3>
                        <canvas id="reservationsChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Statistiky technických problémů -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title">Statistiky technických problémů</h3>
                        <canvas id="issuesChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Nejaktivnější uživatelé -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title">Nejaktivnější uživatelé</h3>
                        <canvas id="usersChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Nejpopulárnější zařízení -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title">Nejpopulárnější zařízení</h3>
                        <canvas id="devicesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailní statistiky -->
        <div class="row">
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title">Detailní statistiky</h3>
                        <div class="row">
                            <?php
                            // Celkový počet rezervací
                            $total_reservations = $conn->query("SELECT COUNT(*) as count FROM reservations")->fetch_assoc()['count'];
                            
                            // Celkový počet technických problémů
                            $total_issues = $conn->query("SELECT COUNT(*) as count FROM technical_issues")->fetch_assoc()['count'];
                            
                            // Průměrný počet rezervací na uživatele
                            $avg_reservations = $conn->query("SELECT AVG(count) as avg FROM (
                                SELECT user_id, COUNT(*) as count FROM reservations GROUP BY user_id
                            ) as counts")->fetch_assoc()['avg'];
                            
                            // Nejčastější hodina rezervací
                            $most_common_hour = $conn->query("SELECT hour, COUNT(*) as count 
                                FROM reservations 
                                GROUP BY hour 
                                ORDER BY count DESC 
                                LIMIT 1")->fetch_assoc();
                            ?>
                            <div class="col-md-3">
                                <div class="stat-card">
                                    <h4><?= number_format($total_reservations) ?></h4>
                                    <p>Celkem rezervací</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-card">
                                    <h4><?= number_format($total_issues) ?></h4>
                                    <p>Celkem technických problémů</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-card">
                                    <h4><?= number_format($avg_reservations, 1) ?></h4>
                                    <p>Průměr rezervací na uživatele</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-card">
                                    <h4><?= $most_common_hour['hour'] ?>. hodina</h4>
                                    <p>Nejčastější hodina rezervací</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Data pro grafy
            <?php
            // Data pro rezervace za posledních 7 dní
            $reservations_data = $conn->query("SELECT DATE(date) as date, COUNT(*) as count 
                FROM reservations 
                WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                GROUP BY DATE(date)
                ORDER BY date");
            $reservations_labels = [];
            $reservations_counts = [];
            while ($row = $reservations_data->fetch_assoc()) {
                $reservations_labels[] = date('d.m.', strtotime($row['date']));
                $reservations_counts[] = $row['count'];
            }

            // Data pro technické problémy podle naléhavosti
            $issues_data = $conn->query("SELECT urgency, COUNT(*) as count 
                FROM technical_issues 
                GROUP BY urgency");
            $issues_labels = [];
            $issues_counts = [];
            while ($row = $issues_data->fetch_assoc()) {
                $issues_labels[] = $row['urgency'];
                $issues_counts[] = $row['count'];
            }

            // Data pro nejaktivnější uživatele
            $users_data = $conn->query("SELECT u.name, COUNT(*) as count 
                FROM reservations r
                JOIN users u ON r.user_id = u.id
                GROUP BY r.user_id
                ORDER BY count DESC
                LIMIT 5");
            $users_labels = [];
            $users_counts = [];
            while ($row = $users_data->fetch_assoc()) {
                $users_labels[] = $row['name'];
                $users_counts[] = $row['count'];
            }

            // Data pro nejpopulárnější zařízení
            $devices_data = $conn->query("SELECT d.device_name, COUNT(*) as count 
                FROM reservations r
                JOIN devices d ON r.device_id = d.id
                GROUP BY r.device_id
                ORDER BY count DESC
                LIMIT 5");
            $devices_labels = [];
            $devices_counts = [];
            while ($row = $devices_data->fetch_assoc()) {
                $devices_labels[] = $row['device_name'];
                $devices_counts[] = $row['count'];
            }
            ?>

            // Graf rezervací
            new Chart(document.getElementById('reservationsChart'), {
                type: 'line',
                data: {
                    labels: <?= json_encode($reservations_labels) ?>,
                    datasets: [{
                        label: 'Počet rezervací',
                        data: <?= json_encode($reservations_counts) ?>,
                        borderColor: '#2563eb',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Rezervace za posledních 7 dní'
                        }
                    }
                }
            });

            // Graf technických problémů
            new Chart(document.getElementById('issuesChart'), {
                type: 'pie',
                data: {
                    labels: <?= json_encode($issues_labels) ?>,
                    datasets: [{
                        data: <?= json_encode($issues_counts) ?>,
                        backgroundColor: ['#0dcaf0', '#ffc107', '#dc3545']
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Technické problémy podle naléhavosti'
                        }
                    }
                }
            });

            // Graf nejaktivnějších uživatelů
            new Chart(document.getElementById('usersChart'), {
                type: 'bar',
                data: {
                    labels: <?= json_encode($users_labels) ?>,
                    datasets: [{
                        label: 'Počet rezervací',
                        data: <?= json_encode($users_counts) ?>,
                        backgroundColor: '#2563eb'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Top 5 nejaktivnějších uživatelů'
                        }
                    }
                }
            });

            // Graf nejpopulárnějších zařízení
            new Chart(document.getElementById('devicesChart'), {
                type: 'bar',
                data: {
                    labels: <?= json_encode($devices_labels) ?>,
                    datasets: [{
                        label: 'Počet rezervací',
                        data: <?= json_encode($devices_counts) ?>,
                        backgroundColor: '#16a34a'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Top 5 nejpopulárnějších zařízení'
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>

<footer class="footer mt-auto py-3 bg-light">
    <div class="container text-center">
        <span class="text-muted">Rezervo by Kryštof Tůma 2025</span>
    </div>
</footer> 