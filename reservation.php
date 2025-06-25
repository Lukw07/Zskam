<?php
require_once 'auth.php';
require_once 'db.php';
redirect_if_not_logged_in();

// Nastavení časové zóny na Prahu
date_default_timezone_set('Europe/Prague');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';
$success = '';
if (isset($_SESSION['reservation_success'])) {
    $success = $_SESSION['reservation_success'];
    unset($_SESSION['reservation_success']);
}
if (isset($_SESSION['reservation_error'])) {
    $error = $_SESSION['reservation_error'];
    unset($_SESSION['reservation_error']);
}
$devices = $conn->query("SELECT * FROM devices");

// Získání vybraného data a zařízení
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$selected_device = isset($_GET['device_id']) ? intval($_GET['device_id']) : null;

// Získání času začátku a konce hodin
$hours = $conn->query("SELECT * FROM hours ORDER BY hour_number");

// Výpočet maximálního možného počtu kusů pro rezervaci na celý den
$max_all_day_quantity = null;
$all_day_unavailable = false;
if ($selected_device && $selected_date) {
    $hours_stmt = $conn->query("SELECT hour_number, end_time FROM hours ORDER BY hour_number");
    $all_hours = [];
    $current_time = new DateTime();
    $reservation_date = new DateTime($selected_date);
    $is_today = $reservation_date->format('Y-m-d') === $current_time->format('Y-m-d');
    while ($h = $hours_stmt->fetch_assoc()) {
        $hour_end = new DateTime($selected_date . ' ' . $h['end_time']);
        if (!$is_today || $hour_end > $current_time) {
            $all_hours[] = $h['hour_number'];
        }
    }
    if (count($all_hours) === 0) {
        $max_all_day_quantity = 0;
        $all_day_unavailable = true;
    } else {
        $min_available = null;
        foreach ($all_hours as $hour) {
            $stmt = $conn->prepare("SELECT (SELECT total_quantity FROM devices WHERE id = ?) - COALESCE((SELECT SUM(quantity) FROM reservations WHERE device_id = ? AND date = ? AND hour = ?), 0) AS available");
            $stmt->bind_param("iisi", $selected_device, $selected_device, $selected_date, $hour);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $available = isset($row['available']) ? (int)$row['available'] : 0;
            if ($min_available === null || $available < $min_available) {
                $min_available = $available;
            }
        }
        $max_all_day_quantity = max(0, $min_available);
        if ($min_available <= 0) {
            $all_day_unavailable = true;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $device_id = intval($_POST['device_id']);
    $date = $_POST['date'];
    $quantity = intval($_POST['quantity']);
    $all_day = isset($_POST['all_day']) && $_POST['all_day'] == '1';

    if ($all_day) {
        // Rezervace na celý den
        $hours_stmt = $conn->query("SELECT hour_number FROM hours ORDER BY hour_number");
        $all_hours = [];
        while ($h = $hours_stmt->fetch_assoc()) {
            $all_hours[] = $h['hour_number'];
        }
        $unavailable = [];
        foreach ($all_hours as $hour) {
            $stmt = $conn->prepare("SELECT (SELECT total_quantity FROM devices WHERE id = ?) - COALESCE((SELECT SUM(quantity) FROM reservations WHERE device_id = ? AND date = ? AND hour = ?), 0) AS available");
            $stmt->bind_param("iisi", $device_id, $device_id, $date, $hour);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $available = isset($row['available']) ? (int)$row['available'] : 0;
            if ($available < $quantity) {
                $unavailable[] = $hour;
            }
        }
        if (count($unavailable) > 0) {
            $_SESSION['reservation_error'] = "Nedostatečný počet zařízení v hodinách: " . implode(", ", $unavailable);
            header("Location: reservation.php?date=" . urlencode($date) . "&device_id=" . urlencode($device_id));
            exit();
        } else {
            // Vše dostupné, vytvořit rezervace pro všechny hodiny
            foreach ($all_hours as $hour) {
                $stmt = $conn->prepare("INSERT INTO reservations (user_id, device_id, date, hour, quantity) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("iisii", $_SESSION['user_id'], $device_id, $date, $hour, $quantity);
                $stmt->execute();
            }
            $_SESSION['reservation_success'] = "Rezervace na celý den byla úspěšně vytvořena.";
            header("Location: reservation.php?date=" . urlencode($date) . "&device_id=" . urlencode($device_id));
            exit();
        }
    } else {
        $hour = intval($_POST['hour']);
        // Validace vstupů
        $date_obj = DateTime::createFromFormat('Y-m-d', $date);
        if ($date_obj === false) {
            $_SESSION['reservation_error'] = "Neplatný formát data";
            header("Location: reservation.php?date=" . urlencode($date) . "&device_id=" . urlencode($device_id));
            exit();
        } else {
            $day_of_week = $date_obj->format('N');
            $max_date = date('Y-m-d', strtotime('+7 days'));

            if ($day_of_week >= 6) {
                $_SESSION['reservation_error'] = "Nelze rezervovat o víkendu";
                header("Location: reservation.php?date=" . urlencode($date) . "&device_id=" . urlencode($device_id));
                exit();
            } elseif ($date > $max_date) {
                $_SESSION['reservation_error'] = "Maximálně 7 dní dopředu";
                header("Location: reservation.php?date=" . urlencode($date) . "&device_id=" . urlencode($device_id));
                exit();
            } elseif ($quantity <= 0) {
                $_SESSION['reservation_error'] = "Neplatný počet kusů";
                header("Location: reservation.php?date=" . urlencode($date) . "&device_id=" . urlencode($device_id));
                exit();
            } else {
                // Kontrola dostupnosti
                $stmt = $conn->prepare("SELECT (SELECT total_quantity FROM devices WHERE id = ?) - COALESCE((SELECT SUM(quantity) FROM reservations WHERE device_id = ? AND date = ? AND hour = ?), 0) AS available");
                $stmt->bind_param("iisi", $device_id, $device_id, $date, $hour);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $available = isset($row['available']) ? (int)$row['available'] : 0;

                if ($available < $quantity) {
                    $_SESSION['reservation_error'] = "Nedostatečný počet zařízení (dostupných: $available)";
                    header("Location: reservation.php?date=" . urlencode($date) . "&device_id=" . urlencode($device_id));
                    exit();
                } else {
                    // Vytvoření nové rezervace
                    $stmt = $conn->prepare("INSERT INTO reservations (user_id, device_id, date, hour, quantity) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("iisii", $_SESSION['user_id'], $device_id, $date, $hour, $quantity);
                    if ($stmt->execute()) {
                        $_SESSION['reservation_success'] = "Rezervace úspěšně vytvořena";
                        header("Location: reservation.php?date=" . urlencode($date) . "&device_id=" . urlencode($device_id));
                        exit();
                    } else {
                        $_SESSION['reservation_error'] = "Chyba při vytváření rezervace";
                        header("Location: reservation.php?date=" . urlencode($date) . "&device_id=" . urlencode($device_id));
                        exit();
                    }
                }
            }
        }
    }
}

// Odstraním načítání editované rezervace
$edit_reservation = null;
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rezervo - Rezervace zařízení</title>
    <link rel="icon" type="image/png" href="logo1.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <style>
        /* Styly pro výběr data */
        input[type="date"] {
            padding: 0.5rem;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            width: 100%;
            cursor: pointer;
        }
        input[type="date"]::-webkit-calendar-picker-indicator {
            cursor: pointer;
            padding: 0.5rem;
        }
        
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
            
            .table {
                font-size: 0.9rem;
            }
            
            .table td, .table th {
                padding: 0.5rem;
            }
            
            .progress {
                height: 15px !important;
                margin-right: 0.5rem !important;
            }
            
            .badge {
                font-size: 0.8rem;
                padding: 0.4rem 0.6rem;
            }
            
            .btn-sm {
                padding: 0.25rem 0.5rem;
                font-size: 0.8rem;
            }
            
            /* Úprava modálního okna pro mobilní zařízení */
            .modal-dialog {
                margin: 0.5rem;
                max-width: calc(100vw - 1rem);
            }
            
            .modal-body {
                padding: 1rem;
            }
            
            .modal-footer {
                padding: 0.75rem;
            }
            
            /* Úprava formuláře */
            .form-label {
                font-size: 0.9rem;
                margin-bottom: 0.3rem;
            }
            
            .form-control, .form-select {
                font-size: 0.9rem;
                padding: 0.4rem 0.75rem;
            }
            
            /* Úprava input group pro mobilní */
            .input-group {
                flex-wrap: nowrap;
            }
            
            .input-group .form-control {
                min-width: 0;
                flex: 1;
            }
            
            .input-group-text {
                white-space: nowrap;
            }
            
            /* Lepší styly pro quantity selector */
            .quantity-selector {
                display: flex;
                align-items: center;
                gap: 1rem;
                margin: 1rem 0;
                justify-content: center;
            }
            
            .quantity-btn {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                border: 3px solid #007bff;
                background: white;
                color: #007bff;
                font-weight: bold;
                font-size: 1.2rem;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                transition: all 0.2s;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            }
            
            .quantity-btn:hover {
                background: #007bff;
                color: white;
                transform: scale(1.05);
                box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            }
            
            .quantity-btn:disabled {
                opacity: 0.5;
                cursor: not-allowed;
                transform: none;
                box-shadow: none;
            }
            
            .quantity-display {
                min-width: 60px;
                text-align: center;
                font-size: 1.2rem;
                padding: 0.5rem;
                border: 2px solid #ced4da;
                border-radius: 0.5rem;
                background: #f8f9fa;
                font-weight: bold;
                box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);
            }
            
            /* Úprava tlačítek */
            .btn {
                padding: 0.4rem 0.75rem;
                font-size: 0.9rem;
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
            
            /* Úprava tabulky dostupnosti */
            .d-flex.align-items-center {
                flex-wrap: wrap;
                gap: 0.5rem;
            }
            
            .progress {
                min-width: 100px;
            }
            
            /* Úprava tooltipů */
            .tooltip {
                font-size: 0.8rem;
            }
            
            /* Úprava input group */
            .input-group-text {
                font-size: 0.9rem;
                padding: 0.4rem 0.75rem;
            }
            
            /* Úprava alertů */
            .alert {
                padding: 0.75rem;
                margin-bottom: 1rem;
                font-size: 0.9rem;
            }
            
            /* Úprava malého textu */
            small {
                font-size: 0.8rem;
            }
        }
        
        /* Časová zona info */
        .timezone-info {
            font-size: 0.8rem;
            color: #6c757d;
            text-align: center;
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <h2>Nová rezervace</h2>
        <div class="timezone-info">
            Čas: Praha (UTC+1) - <?= date('d.m.Y H:i:s') ?>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php elseif ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-body">
                <h3 class="card-title">1. Výběr data a zařízení</h3>
                <form method="get" class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Datum:</label>
                        <input type="date" name="date" id="datePicker" class="form-control" 
                            value="<?= $selected_date ?>"
                            min="<?= date('Y-m-d') ?>"
                            max="<?= date('Y-m-d', strtotime('+7 days')) ?>"
                            onchange="this.form.submit()"
                            required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Zařízení:</label>
                        <select name="device_id" id="deviceSelect" class="form-select" required onchange="this.form.submit()">
                            <option value="">Vyberte zařízení</option>
                            <?php while ($device = $devices->fetch_assoc()): ?>
                                <option value="<?= $device['id'] ?>" 
                                        data-total="<?= $device['total_quantity'] ?>"
                                        <?= $selected_device == $device['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($device['device_name']) ?> (<?= $device['total_quantity'] ?> ks)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </form>
                <?php if ($selected_device && $selected_date): ?>
                    <div class="mt-3">
                        <strong>Maximální možný počet kusů pro rezervaci na celý den:</strong>
                        <?php if ($all_day_unavailable): ?>
                            <span class="text-danger">Není možné rezervovat na celý den (v některé hodině není zařízení dostupné).</span>
                        <?php else: ?>
                            <span class="text-success"><?= $max_all_day_quantity ?></span>
                        <?php endif; ?>
                    </div>
                    <form method="post" class="mt-3" id="allday-form">
                        <input type="hidden" name="device_id" value="<?= $selected_device ?>">
                        <input type="hidden" name="date" value="<?= $selected_date ?>">
                        <input type="hidden" name="all_day" value="1">
                        <div class="mb-2">
                            <label class="form-label">Počet kusů:</label>
                            <input type="number" class="form-control" name="quantity" min="1" max="<?= $max_all_day_quantity ?>" value="<?= $max_all_day_quantity > 0 ? 1 : 0 ?>" <?= $all_day_unavailable ? 'disabled' : '' ?> required>
                            <small class="text-muted">Maximální možný počet: <?= $max_all_day_quantity ?></small>
                        </div>
                        <button type="submit" class="btn btn-primary" id="allday-submit" <?= $all_day_unavailable ? 'disabled' : '' ?>>Rezervovat na celý den</button>
                    </form>
                    <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        var alldayForm = document.getElementById('allday-form');
                        if (alldayForm) {
                            alldayForm.addEventListener('submit', function(e) {
                                var submitBtn = document.getElementById('allday-submit');
                                submitBtn.disabled = true;
                                submitBtn.textContent = 'Odesílám...';
                            });
                        }
                    });
                    </script>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($selected_device): ?>
        <div class="card">
            <div class="card-body">
                <h3 class="card-title">2. Výběr hodiny</h3>
                <?php
                // Kontrola, zda všechny hodiny již proběhly
                $hours->data_seek(0);
                $all_hours_past = true;
                $current_time = new DateTime();
                $reservation_date = new DateTime($selected_date);
                $is_today = $reservation_date->format('Y-m-d') === $current_time->format('Y-m-d');
                $future_hours_exist = false;
                while ($hour = $hours->fetch_assoc()) {
                    $hour_end = new DateTime($selected_date . ' ' . $hour['end_time']);
                    if (!$is_today || $hour_end > $current_time) {
                        $all_hours_past = false;
                        break;
                    }
                }
                if ($all_hours_past && $is_today): ?>
                    <div class="alert alert-info">
                        <h4 class="alert-heading">Dnes již skončila možnost rezervace</h4>
                        <p>Všechny hodiny pro dnešní den již proběhly. Prosím, vyberte si jiný den pro rezervaci.</p>
                    </div>
                <?php endif; ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Hodina</th>
                                <th>Čas</th>
                                <th>Dostupnost</th>
                                <th>Akce</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $hours->data_seek(0);
                            $hour_index = 0;
                            $next_hour_found = false;
                            while ($hour = $hours->fetch_assoc()): 
                                $hour_start = new DateTime($selected_date . ' ' . $hour['start_time']);
                                $hour_end = new DateTime($selected_date . ' ' . $hour['end_time']);
                                $is_past = ($reservation_date < $current_time->format('Y-m-d')) || ($is_today && $hour_end <= $current_time);
                                $is_next = false;
                                if (!$is_past && !$next_hour_found) {
                                    $is_next = true;
                                    $next_hour_found = true;
                                }
                                $row_class = $is_past ? 'past-hour-row' : ($is_next ? 'next-hour-row' : '');

                                // Kontrola dostupnosti pro každou hodinu
                                $stmt = $conn->prepare("SELECT 
                                    (SELECT total_quantity FROM devices WHERE id = ?) as total,
                                    (SELECT total_quantity FROM devices WHERE id = ?) - 
                                    COALESCE(SUM(quantity), 0) AS available,
                                    GROUP_CONCAT(CONCAT(u.name, ' (', r.quantity, ' ks)') SEPARATOR '\n') as reservations
                                    FROM reservations r
                                    JOIN users u ON r.user_id = u.id
                                    WHERE r.device_id = ? AND r.date = ? AND r.hour = ?
                                    GROUP BY r.device_id, r.date, r.hour");
                                $stmt->bind_param("iiisi", $selected_device, $selected_device, $selected_device, $selected_date, $hour['hour_number']);
                                $stmt->execute();
                                $result = $stmt->get_result()->fetch_assoc();
                                
                                // Získání celkového počtu zařízení
                                $stmt = $conn->prepare("SELECT total_quantity FROM devices WHERE id = ?");
                                $stmt->bind_param("i", $selected_device);
                                $stmt->execute();
                                $total = $stmt->get_result()->fetch_assoc()['total_quantity'];
                                
                                // Nastavení výchozích hodnot
                                $available = $total;
                                $reservations = null;
                                
                                // Aktualizace hodnot z výsledku dotazu, pokud existují
                                if ($result) {
                                    $available = $result['available'];
                                    $reservations = $result['reservations'];
                                }
                                
                                // Výpočet procentuální dostupnosti
                                $percentage = ($total > 0) ? ($available / $total) * 100 : 0;
                                
                                // Určení barvy podle dostupnosti
                                $color_class = 'success';
                                if ($percentage <= 25) {
                                    $color_class = 'danger';
                                } elseif ($percentage <= 50) {
                                    $color_class = 'warning';
                                } elseif ($percentage <= 75) {
                                    $color_class = 'info';
                                }
                            ?>
                            <tr class="hour-row <?= $row_class ?>" style="opacity:0; transform: translateY(20px); transition: opacity 0.6s cubic-bezier(0.4,0,0.2,1), transform 0.6s cubic-bezier(0.4,0,0.2,1); transition-delay: <?= ($hour_index * 80) ?>ms;">
                                <td><?= $hour['hour_number'] ?>. hodina<?php if ($is_past): ?><span class="badge badge-past-hour ms-2">proběhlo</span><?php elseif ($is_next): ?><span class="badge badge-next-hour ms-2">nadcházející hodina</span><?php endif; ?></td>
                                <td><?= date('H:i', strtotime($hour['start_time'])) ?> - <?= date('H:i', strtotime($hour['end_time'])) ?></td>
                                <td>
                                    <div class="d-flex align-items-center flex-wrap">
                                        <?php if ($is_past): ?>
                                            <div class="progress flex-grow-1 animated-progress" style="height: 20px; border-radius: 12px; overflow: hidden;">
                                                <div class="progress-bar bg-secondary"
                                                     role="progressbar"
                                                     data-value="100"
                                                     style="width: 100%"
                                                     data-bs-toggle="tooltip"
                                                     data-bs-html="true"
                                                     title="Tato hodina již proběhla">
                                                     Proběhlo
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="progress flex-grow-1 animated-progress" style="height: 20px; border-radius: 12px; overflow: hidden;">
                                                <div class="progress-bar bg-<?= $color_class ?>"
                                                     role="progressbar"
                                                     data-value="<?= $percentage ?>"
                                                     style="width: 0%"
                                                     data-bs-toggle="tooltip"
                                                     data-bs-html="true"
                                                     title="<?= $reservations ? 'Rezervace:<br>' . nl2br(htmlspecialchars($reservations)) : 'Žádné rezervace' ?>">
                                                    <?= $available ?>/<?= $total ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <span class="badge bg-<?= $is_past ? 'secondary' : $color_class ?> ms-2">
                                            <?= $is_past ? 'Proběhlo' : $available . ' kusů' ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="align-middle">
                                    <?php if ($is_past): ?>
                                        <button type="button" class="btn btn-secondary btn-sm" disabled>Proběhlo</button>
                                    <?php elseif ($available > 0): ?>
                                        <button type="button" class="btn btn-primary btn-sm" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#reservationModal"
                                                data-hour="<?= $hour['hour_number'] ?>"
                                                data-available="<?= $available ?>"
                                                data-date="<?= $selected_date ?>"
                                                data-time="<?= date('H:i', strtotime($hour['start_time'])) ?> - <?= date('H:i', strtotime($hour['end_time'])) ?>">
                                            Rezervovat
                                        </button>
                                    <?php else: ?>
                                    <button type="button" class="btn btn-secondary btn-sm" disabled>Nedostupné</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php $hour_index++; endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Modal pro rezervaci -->
    <div class="modal fade" id="reservationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Vytvořit rezervaci</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="device_id" value="<?= $selected_device ?>">
                        <input type="hidden" name="date" value="<?= $selected_date ?>">
                        <input type="hidden" name="hour" id="modalHour">
                        <div class="mb-3">
                            <p class="mb-2"><strong>Datum:</strong> <span id="modalDate"></span></p>
                            <p class="mb-2"><strong>Čas:</strong> <span id="modalTime"></span></p>
                            <p class="mb-2"><strong>Zařízení:</strong> <span id="modalDevice"></span></p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Počet kusů:</label>
                            <div class="quantity-selector d-flex align-items-center justify-content-center">
                                <button type="button" class="quantity-btn" id="decreaseBtn" onclick="changeQuantity(-1)">−</button>
                                <div class="quantity-display" id="quantityDisplay">1</div>
                                <button type="button" class="quantity-btn" id="increaseBtn" onclick="changeQuantity(1)">+</button>
                            </div>
                            <input type="hidden" name="quantity" id="quantityInput" value="1">
                            <small class="text-muted d-block text-center">Dostupných: <span id="modalAvailable"></span> kusů</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zrušit</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Vytvořit rezervaci
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentQuantity = 1;
        let maxAvailable = 0;

        function changeQuantity(change) {
            const newQuantity = currentQuantity + change;
            if (newQuantity >= 1 && newQuantity <= maxAvailable) {
                currentQuantity = newQuantity;
                document.getElementById('quantityDisplay').textContent = currentQuantity;
                document.getElementById('quantityInput').value = currentQuantity;
                
                // Aktualizace tlačítek
                document.getElementById('decreaseBtn').disabled = currentQuantity <= 1;
                document.getElementById('increaseBtn').disabled = currentQuantity >= maxAvailable;
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const availabilityTable = document.getElementById('availability-table');
            if (availabilityTable) {
                setupAutoRefresh('availability-table', 'reservation.php', 60000);
            }

            // Inicializace tooltipů
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl, {
                    html: true,
                    placement: 'top'
                });
            });

            const modal = document.getElementById('reservationModal');

            modal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const hour = button.getAttribute('data-hour');
                const available = parseInt(button.getAttribute('data-available'));
                const date = button.getAttribute('data-date');
                const time = button.getAttribute('data-time');
                const deviceName = document.querySelector('#deviceSelect option:checked').textContent.split(' (')[0];

                document.getElementById('modalHour').value = hour;
                document.getElementById('modalAvailable').textContent = available;
                document.getElementById('modalDate').textContent = new Date(date).toLocaleDateString('cs-CZ');
                document.getElementById('modalTime').textContent = time;
                document.getElementById('modalDevice').textContent = deviceName;
                
                // Resetování quantity selectoru
                maxAvailable = available;
                currentQuantity = 1;
                document.getElementById('quantityDisplay').textContent = currentQuantity;
                document.getElementById('quantityInput').value = currentQuantity;
                
                // Aktualizace tlačítek
                document.getElementById('decreaseBtn').disabled = currentQuantity <= 1;
                document.getElementById('increaseBtn').disabled = currentQuantity >= maxAvailable;
            });

            // Zakázání víkendů v date pickeru
            const datePicker = document.getElementById('datePicker');
            datePicker.addEventListener('input', function() {
                const selectedDate = new Date(this.value);
                const dayOfWeek = selectedDate.getDay();
                
                if (dayOfWeek === 0 || dayOfWeek === 6) {
                    alert('Nelze vybrat víkend. Prosím vyberte pracovní den.');
                    this.value = '';
                }
            });

            // Animace postupného načítání řádků hodin
            document.querySelectorAll('.hour-row').forEach(function(row, idx) {
                setTimeout(function() {
                    row.style.opacity = 1;
                    row.style.transform = 'translateY(0)';
                }, idx * 80 + 200);
            });
            // Animace progress barů
            document.querySelectorAll('.animated-progress .progress-bar').forEach(function(bar) {
                const value = bar.getAttribute('data-value');
                setTimeout(function() {
                    bar.style.width = value + '%';
                }, 400);
            });
        });
    </script>
    <script>
        // Automatické obnovení stránky každou minutu
        setInterval(function() {
            window.location.reload();
        }, 60000); // 60000 ms = 1 minuta
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>