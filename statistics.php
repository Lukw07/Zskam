<?php
require_once 'auth.php';
require_once 'db.php';
redirect_if_not_logged_in();
if (!is_admin()) die("Přístup odepřen");

// Načtení dat pro grafy
// Statistiky rezervací za posledních 7 dní
$reservations_data = $conn->query("
    SELECT DATE(date) as date, COUNT(*) as count 
    FROM reservations 
    WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(date)
    ORDER BY date
")->fetch_all(MYSQLI_ASSOC);

// Statistiky technických problémů podle stavu
$issues_data = $conn->query("
    SELECT status, COUNT(*) as count 
    FROM technical_issues 
    GROUP BY status
")->fetch_all(MYSQLI_ASSOC);

// Nejaktivnější uživatelé (top 5)
$users_data = $conn->query("
    SELECT u.name, COUNT(r.id) as count 
    FROM users u 
    LEFT JOIN reservations r ON u.id = r.user_id 
    GROUP BY u.id 
    ORDER BY count DESC 
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Nejpopulárnější zařízení (top 5)
$devices_data = $conn->query("
    SELECT d.device_name, COUNT(r.id) as count 
    FROM devices d 
    LEFT JOIN reservations r ON d.id = r.device_id 
    GROUP BY d.id 
    ORDER BY count DESC 
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rezervo - Statistiky</title>
    <link rel="icon" type="image/png" href="logo1.png">
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
        // Inicializace grafů
        document.addEventListener('DOMContentLoaded', function() {
            // Statistiky rezervací
            new Chart(document.getElementById('reservationsChart'), {
                type: 'line',
                data: {
                    labels: <?= json_encode(array_column($reservations_data, 'date')) ?>,
                    datasets: [{
                        label: 'Počet rezervací',
                        data: <?= json_encode(array_column($reservations_data, 'count')) ?>,
                        borderColor: 'rgb(75, 192, 192)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Vývoj rezervací v čase'
                        }
                    }
                }
            });

            // Statistiky technických problémů
            new Chart(document.getElementById('issuesChart'), {
                type: 'bar',
                data: {
                    labels: <?= json_encode(array_column($issues_data, 'status')) ?>,
                    datasets: [{
                        label: 'Počet problémů',
                        data: <?= json_encode(array_column($issues_data, 'count')) ?>,
                        backgroundColor: 'rgba(255, 99, 132, 0.5)'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Technické problémy podle stavu'
                        }
                    }
                }
            });

            // Nejaktivnější uživatelé
            new Chart(document.getElementById('usersChart'), {
                type: 'pie',
                data: {
                    labels: <?= json_encode(array_column($users_data, 'name')) ?>,
                    datasets: [{
                        data: <?= json_encode(array_column($users_data, 'count')) ?>,
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.5)',
                            'rgba(54, 162, 235, 0.5)',
                            'rgba(255, 206, 86, 0.5)',
                            'rgba(75, 192, 192, 0.5)',
                            'rgba(153, 102, 255, 0.5)'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Nejaktivnější uživatelé'
                        }
                    }
                }
            });

            // Nejpopulárnější zařízení
            new Chart(document.getElementById('devicesChart'), {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode(array_column($devices_data, 'device_name')) ?>,
                    datasets: [{
                        data: <?= json_encode(array_column($devices_data, 'count')) ?>,
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.5)',
                            'rgba(54, 162, 235, 0.5)',
                            'rgba(255, 206, 86, 0.5)',
                            'rgba(75, 192, 192, 0.5)',
                            'rgba(153, 102, 255, 0.5)'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Nejpopulárnější zařízení'
                        }
                    }
                }
            });
        });
    </script>
    <?php include 'footer.php'; ?>
</body>
</html> 