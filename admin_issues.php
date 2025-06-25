<?php
require_once 'auth.php';
require_once 'db.php';

redirect_if_not_logged_in();
if (!is_admin()) die("Přístup odepřen");

// Zpracování změny stavu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status') {
        $issue_id = intval($_POST['issue_id']);
        $new_status = isset($_POST['status']) ? $conn->real_escape_string($_POST['status']) : '';
        
        // Aktualizace stavu v databázi
        $stmt = $conn->prepare("UPDATE technical_issues SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $issue_id);
        
        if ($stmt->execute()) {
            // Odeslat email při změně stavu na 'vyřešeno'
            if ($new_status === 'vyřešeno') {
                // Získat info o incidentu a uživateli
                $stmt2 = $conn->prepare("SELECT ti.class, ti.description, ti.urgency, ti.created_at, u.email, u.name FROM technical_issues ti JOIN users u ON ti.user_id = u.id WHERE ti.id = ?");
                $stmt2->bind_param("i", $issue_id);
                $stmt2->execute();
                $incident = $stmt2->get_result()->fetch_assoc();
                if ($incident) {
                    require_once 'vendor/autoload.php';
                    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                    try {
                        $mail_config = require 'config.php';
                        $mail->isSMTP();
                        $mail->Host = $mail_config['smtp']['host'];
                        $mail->SMTPAuth = true;
                        $mail->Username = $mail_config['smtp']['username'];
                        $mail->Password = $mail_config['smtp']['password'];
                        $mail->SMTPSecure = $mail_config['smtp']['encryption'];
                        $mail->Port = $mail_config['smtp']['port'];
                        $mail->CharSet = $mail_config['smtp']['charset'];
                        $mail->setFrom($mail_config['smtp']['from_email'], $mail_config['smtp']['from_name']);
                        $mail->addAddress($incident['email'], $incident['name']);
                        $mail->isHTML(true);
                        $mail->Subject = 'Váš technický problém byl vyřešen';

                        // Moderní HTML vzhled emailu
                        $incident_id = sprintf('INC-%06d', $issue_id);
                        $current_time = date('d.m.Y H:i');
                        $urgency = ucfirst($incident['urgency']);
                        $class = htmlspecialchars($incident['class']);
                        $description = htmlspecialchars($incident['description']);
                        $created_at = date('d.m.Y H:i', strtotime($incident['created_at']));

                        $mail->Body = "
<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Vyřešený incident</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8fafc; color: #1f2937; }
        .email-container { max-width: 600px; margin: 20px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border: 1px solid #e5e7eb; }
        .header { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: white; padding: 24px; text-align: center; }
        .logo-section { width: 100%; margin-bottom: 16px; }
        .logo { width: 100%; max-width: 150px; height: auto; margin: 0 auto; display: block; }
        .company-info { margin-top: 16px; }
        .company-info h1 { font-size: 20px; font-weight: 600; margin-bottom: 4px; }
        .company-info p { font-size: 14px; opacity: 0.9; }
        .urgency-banner { background: #f8fafc; border-left: 4px solid #10b981; padding: 20px; margin-bottom: 20px; }
        .incident-id { background: #1e293b; color: white; padding: 8px 16px; border-radius: 4px; font-family: 'Courier New', monospace; font-size: 14px; font-weight: 600; display: inline-block; margin-top: 8px; }
        .content { padding: 24px; }
        .section { margin-bottom: 24px; }
        .section-title { font-size: 16px; font-weight: 600; color: #1f2937; margin-bottom: 16px; padding-bottom: 8px; border-bottom: 1px solid #e5e7eb; }
        .info-grid { display: grid; grid-template-columns: 1fr; gap: 16px; margin-bottom: 24px; }
        .info-item { background: #f8fafc; padding: 16px; border-radius: 6px; border-left: 3px solid #3b82f6; }
        .info-label { font-size: 13px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; }
        .info-value { font-size: 15px; color: #111827; font-weight: 500; }
        .description-box { background: #fff; border: 1px solid #e5e7eb; border-radius: 6px; padding: 16px; position: relative; }
        .description-box::before { content: 'Popis problému'; position: absolute; top: -10px; left: 16px; background: white; padding: 0 8px; font-size: 13px; font-weight: 600; color: #6b7280; text-transform: uppercase; }
        .description-content { font-size: 14px; line-height: 1.6; color: #374151; }
        .solved-banner { background: linear-gradient(90deg, #d1fae5 0%, #bbf7d0 100%); color: #047857; border-left: 5px solid #10b981; padding: 18px 20px; border-radius: 8px; font-size: 1.1rem; font-weight: 600; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; }
        .solved-badge { background: linear-gradient(90deg, #10b981 0%, #22d3ee 100%); color: #fff; font-size: 1.05rem; padding: 0.5em 1.2em; border-radius: 1.2em; font-weight: 700; box-shadow: 0 2px 8px rgba(16,185,129,0.10); letter-spacing: 0.04em; display: inline-block; margin-right: 10px; }
        .footer { padding: 1.5rem; background-color: #f8fafc; border-bottom-left-radius: 16px; border-bottom-right-radius: 16px; text-align: center; border-top: 1px solid #e5e7eb; }
        .footer-content { display: flex; align-items: center; justify-content: center; gap: 0.75rem; }
        .footer-content img { height: 30px; width: auto; object-fit: contain; }
        .footer-content div { color: #6b7280; font-size: 0.85rem; font-weight: 500; }
    </style>
</head>
<body>
    <div class='email-container'>
        <div class='header'>
            <div class='logo-section'>
                <img src='https://zskamenicka.cz/wp-content/uploads/2025/06/zskam.png' alt='Logo školy' class='logo'>
                <div class='company-info'>
                    <h1>IT Support</h1>
                    <p>ZŠ Kamenická</p>
                </div>
            </div>
        </div>
        <div class='urgency-banner'>
            <div class='incident-id'>Incident ID: {$incident_id}</div>
        </div>
        <div class='solved-banner'>
            <span class='solved-badge'>Vyřešeno</span>
            Váš technický problém byl úspěšně vyřešen.
        </div>
        <div class='content'>
            <div class='section'>
                <h2 class='section-title'>Detail incidentu</h2>
                <div class='info-grid'>
                    <div class='info-item'>
                        <div class='info-label'>Třída</div>
                        <div class='info-value'>{$class}</div>
                    </div>
                    <div class='info-item'>
                        <div class='info-label'>Priorita</div>
                        <div class='info-value'>{$urgency}</div>
                    </div>
                </div>
                <div class='description-box'>
                    <div class='description-content'>{$description}</div>
                </div>
            </div>
            <div class='section'>
                <h2 class='section-title'>Nahlášeno</h2>
                <div class='info-grid'>
                    <div class='info-item'>
                        <div class='info-label'>Datum nahlášení</div>
                        <div class='info-value'>{$created_at}</div>
                    </div>
                    <div class='info-item'>
                        <div class='info-label'>Vyřešeno</div>
                        <div class='info-value'>{$current_time}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class='footer'>
            <div class='footer-content'>
                <img src='https://zskamenicka.cz/wp-content/uploads/2025/06/logo_bezpozadi_white.webp' alt='Rezervo Logo' style='height: 40px; display: block; margin: 0 auto;'>
                <div style='margin-top: 12px; font-size: 12px;'>By Kryštof Tůma</div>
                <div style='margin-top: 8px; font-size: 11px; color: #6b7280;'>IT systém pro správu incidentů a rezervací</div>
                <div style='margin-top: 4px; font-size: 10px; color: #9ca3af;'>Tento email byl automaticky vygenerován dne {$current_time}</div>
            </div>
        </div>
    </div>
</body>
</html>
";
                        $mail->AltBody = "Dobrý den, {$incident['name']},\nVáš technický problém byl označen jako vyřešený.\nTřída: {$incident['class']}\nNaléhavost: {$incident['urgency']}\nPopis: {$incident['description']}\nDatum nahlášení: {$created_at}\nVyřešeno: {$current_time}\nDěkujeme za nahlášení a spolupráci. ZŠ Kamenická IT tým";
                        $mail->send();
                    } catch (Exception $e) {
                        // Pokud email selže, pokračuj bez přerušení
                    }
                }
            }
            $success_message = "Stav incidentu byl úspěšně aktualizován";
        } else {
            $error_message = "Chyba při aktualizaci stavu incidentu";
        }
    } elseif ($_POST['action'] === 'delete') {
        $issue_id = intval($_POST['issue_id']);
        
        $stmt = $conn->prepare("DELETE FROM technical_issues WHERE id = ?");
        $stmt->bind_param("i", $issue_id);
        
        if ($stmt->execute()) {
            $success_message = "Problém byl úspěšně smazán";
        } else {
            $error_message = "Chyba při mazání problému";
        }
    }
}

// Lazy loading proměnné
// $issues_limit = 10;
// $issues_offset = isset($_GET['issues_offset']) ? intval($_GET['issues_offset']) : 0;
$sql = "SELECT ti.*, u.name 
       FROM technical_issues ti 
       JOIN users u ON ti.user_id = u.id 
       WHERE ti.status != 'vyřešeno'";
$params = array();
$types = "";

if (!empty($_GET['status'])) {
    $sql .= " AND ti.status = ?";
    $params[] = $_GET['status'];
    $types .= "s";
}

if (!empty($_GET['urgency'])) {
    $sql .= " AND ti.urgency = ?";
    $params[] = $_GET['urgency'];
    $types .= "s";
}

if (!empty($_GET['date_from'])) {
    $sql .= " AND DATE(ti.created_at) >= ?";
    $params[] = $_GET['date_from'];
    $types .= "s";
}

// Přidání řazení
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'created_at_desc';
switch ($sort) {
    case 'created_at_asc':
        $sql .= " ORDER BY ti.created_at ASC";
        break;
    case 'urgency_asc':
        $sql .= " ORDER BY 
            CASE ti.urgency
                WHEN 'vysoká' THEN 1
                WHEN 'střední' THEN 2
                WHEN 'nízká' THEN 3
            END ASC, ti.created_at DESC";
        break;
    case 'urgency_desc':
        $sql .= " ORDER BY 
            CASE ti.urgency
                WHEN 'vysoká' THEN 1
                WHEN 'střední' THEN 2
                WHEN 'nízká' THEN 3
            END DESC, ti.created_at DESC";
        break;
    default:
        $sql .= " ORDER BY ti.created_at DESC";
}

// $sql .= " LIMIT $issues_limit OFFSET $issues_offset";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$issues = $stmt->get_result();
$issues_total = $issues->num_rows;
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rezervo - Technické problémy</title>
    <link rel="icon" type="image/png" href="logo1.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
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

            .table {
                font-size: 0.9rem;
            }

            .table td, .table th {
                padding: 0.5rem;
                white-space: nowrap;
            }

            .table td:nth-child(4) {
                white-space: normal;
                min-width: 150px;
            }

            .badge {
                font-size: 0.8rem;
                padding: 0.4rem 0.6rem;
            }

            .form-select {
                font-size: 0.9rem;
                padding: 0.4rem 0.75rem;
            }

            .btn {
                padding: 0.4rem 0.75rem;
                font-size: 0.9rem;
            }

            /* Úprava filtrů */
            .row.g-3 {
                margin-bottom: 0.5rem;
            }

            .col-md-3 {
                margin-bottom: 0.5rem;
            }

            /* Úprava nadpisů */
            h2 {
                font-size: 1.5rem;
                margin-bottom: 1rem;
            }

            h5 {
                font-size: 1.1rem;
                margin-bottom: 0.75rem;
            }

            /* Úprava alertů */
            .alert {
                padding: 0.75rem;
                margin-bottom: 1rem;
                font-size: 0.9rem;
            }

            /* Úprava selectu pro změnu stavu */
            select[name="status"] {
                font-size: 0.9rem;
                padding: 0.3rem 0.5rem;
            }

            /* Úprava tabulky pro mobilní zobrazení */
            .table-responsive {
                margin: 0 -10px;
                padding: 0 10px;
                width: calc(100% + 20px);
            }

            /* Úprava tlačítek v tabulce */
            .d-inline {
                display: block !important;
                margin-top: 0.5rem;
            }

            /* Úprava form-group */
            .form-group {
                margin-bottom: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4" id="issues-content">
        <h2>Správa technických problémů</h2>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?= $success_message ?></div>
        <?php endif; ?>
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?= $error_message ?></div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Filtry a řazení</h5>
                <form method="get" class="row g-3" id="filterForm">
                    <div class="col-md-3">
                        <label class="form-label">Stav:</label>
                        <select name="status" class="form-select" onchange="this.form.submit()">
                            <option value="">Všechny stavy</option>
                            <option value="nový" <?= isset($_GET['status']) && $_GET['status'] === 'nový' ? 'selected' : '' ?>>Nový</option>
                            <option value="přečteno" <?= isset($_GET['status']) && $_GET['status'] === 'přečteno' ? 'selected' : '' ?>>Přečteno</option>
                            <option value="v řešení" <?= isset($_GET['status']) && $_GET['status'] === 'v řešení' ? 'selected' : '' ?>>V řešení</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Naléhavost:</label>
                        <select name="urgency" class="form-select" onchange="this.form.submit()">
                            <option value="">Všechny naléhavosti</option>
                            <option value="vysoká" <?= isset($_GET['urgency']) && $_GET['urgency'] === 'vysoká' ? 'selected' : '' ?>>Vysoká</option>
                            <option value="střední" <?= isset($_GET['urgency']) && $_GET['urgency'] === 'střední' ? 'selected' : '' ?>>Střední</option>
                            <option value="nízká" <?= isset($_GET['urgency']) && $_GET['urgency'] === 'nízká' ? 'selected' : '' ?>>Nízká</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Řadit podle:</label>
                        <select name="sort" class="form-select" onchange="this.form.submit()">
                            <option value="created_at_desc" <?= (!isset($_GET['sort']) || $_GET['sort'] === 'created_at_desc') ? 'selected' : '' ?>>Nejnovější</option>
                            <option value="created_at_asc" <?= isset($_GET['sort']) && $_GET['sort'] === 'created_at_asc' ? 'selected' : '' ?>>Nejstarší</option>
                            <option value="urgency_asc" <?= isset($_GET['sort']) && $_GET['sort'] === 'urgency_asc' ? 'selected' : '' ?>>Naléhavost (vzestupně)</option>
                            <option value="urgency_desc" <?= isset($_GET['sort']) && $_GET['sort'] === 'urgency_desc' ? 'selected' : '' ?>>Naléhavost (sestupně)</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Od data:</label>
                        <input type="date" name="date_from" class="form-control" value="<?= isset($_GET['date_from']) ? htmlspecialchars($_GET['date_from']) : '' ?>" onchange="this.form.submit()">
                    </div>
                    <div class="col-12">
                        <a href="admin_issues.php" class="btn btn-secondary">Zrušit filtry</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Aktivní problémy -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">Aktivní problémy</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Uživatel</th>
                                <th>Třída</th>
                                <th>Popis</th>
                                <th>Naléhavost</th>
                                <th>Stav</th>
                                <th>Datum nahlášení</th>
                                <th>Akce</th>
                            </tr>
                        </thead>
                        <tbody id="issues-tbody">
                            <?php while ($issue = $issues->fetch_assoc()): ?>
                            <tr class="fade-in-row issue-row issue-priority-<?= $issue['urgency'] ?>">
                                <td><?= $issue['id'] ?></td>
                                <td><?= htmlspecialchars($issue['name']) ?></td>
                                <td><?= htmlspecialchars($issue['class']) ?></td>
                                <td><?= htmlspecialchars($issue['description']) ?></td>
                                <td>
                                    <span class="badge badge-priority badge-priority-<?= $issue['urgency'] ?> d-inline-flex align-items-center" data-bs-toggle="tooltip" title="Priorita: <?= ucfirst($issue['urgency']) ?>">
                                        <?php if ($issue['urgency'] === 'vysoká'): ?>
                                            <i class="fas fa-arrow-up me-1"></i>
                                        <?php elseif ($issue['urgency'] === 'střední'): ?>
                                            <i class="fas fa-arrow-right me-1"></i>
                                        <?php else: ?>
                                            <i class="fas fa-arrow-down me-1"></i>
                                        <?php endif; ?>
                                        <?= $issue['urgency'] ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="post" class="d-inline status-form">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="issue_id" value="<?= $issue['id'] ?>">
                                        <button type="button" class="badge bg-<?= $issue['status'] === 'nový' ? 'primary' : ($issue['status'] === 'přečteno' ? 'secondary' : ($issue['status'] === 'v řešení' ? 'warning' : 'success')) ?> status-badge" data-current-status="<?= $issue['status'] ?>">
                                            <?= $issue['status'] ?>
                                        </button>
                                        <select name="status" class="form-select form-select-sm d-none status-select" style="min-width: 120px;">
                                            <option value="nový" <?= $issue['status'] === 'nový' ? 'selected' : '' ?>>Nový</option>
                                            <option value="přečteno" <?= $issue['status'] === 'přečteno' ? 'selected' : '' ?>>Přečteno</option>
                                            <option value="v řešení" <?= $issue['status'] === 'v řešení' ? 'selected' : '' ?>>V řešení</option>
                                            <option value="vyřešeno" <?= $issue['status'] === 'vyřešeno' ? 'selected' : '' ?>>Vyřešeno</option>
                                        </select>
                                    </form>
                                </td>
                                <td><?= date('d.m.Y H:i', strtotime($issue['created_at'])) ?></td>
                                <td>
                                    <form method="post" class="d-inline mt-1">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="issue_id" value="<?= $issue['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Opravdu chcete smazat tento technický problém?')">
                                            <i class="fas fa-trash"></i> Smazat
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <!-- Tlačítko 'Načíst další' a skeleton loader odstraněny, protože už nejsou potřeba -->
            </div>
        </div>

        <!-- Vyřešené problémy -->
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="card-title mb-0">Vyřešené problémy</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Uživatel</th>
                                <th>Třída</th>
                                <th>Popis</th>
                                <th>Naléhavost</th>
                                <th>Datum nahlášení</th>
                                <th>Datum vyřešení</th>
                                <th>Akce</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Resetování proměnných pro druhý dotaz
                            $params2 = array();
                            $types2 = "";
                            
                            // Sestavení SQL dotazu pro vyřešené problémy
                            $sql2 = "SELECT ti.*, u.name 
                                    FROM technical_issues ti 
                                    JOIN users u ON ti.user_id = u.id 
                                    WHERE ti.status = 'vyřešeno'";
                            
                            if (!empty($_GET['date_from'])) {
                                $sql2 .= " AND DATE(ti.created_at) >= ?";
                                $params2[] = $_GET['date_from'];
                                $types2 .= "s";
                            }

                            // Přidání řazení
                            switch ($sort) {
                                case 'created_at_asc':
                                    $sql2 .= " ORDER BY ti.created_at ASC";
                                    break;
                                case 'urgency_asc':
                                    $sql2 .= " ORDER BY 
                                        CASE ti.urgency
                                            WHEN 'vysoká' THEN 1
                                            WHEN 'střední' THEN 2
                                            WHEN 'nízká' THEN 3
                                        END ASC, ti.created_at DESC";
                                    break;
                                case 'urgency_desc':
                                    $sql2 .= " ORDER BY 
                                        CASE ti.urgency
                                            WHEN 'vysoká' THEN 1
                                            WHEN 'střední' THEN 2
                                            WHEN 'nízká' THEN 3
                                        END DESC, ti.created_at DESC";
                                    break;
                                default:
                                    $sql2 .= " ORDER BY ti.created_at DESC";
                            }

                            $stmt2 = $conn->prepare($sql2);
                            if (!empty($params2)) {
                                $stmt2->bind_param($types2, ...$params2);
                            }
                            $stmt2->execute();
                            $issues2 = $stmt2->get_result();
                            
                            while ($issue = $issues2->fetch_assoc()):
                            ?>
                            <tr>
                                <td><?= $issue['id'] ?></td>
                                <td><?= htmlspecialchars($issue['name']) ?></td>
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
                                <td><?= date('d.m.Y H:i', strtotime($issue['created_at'])) ?></td>
                                <td><?= date('d.m.Y H:i', strtotime($issue['updated_at'] ?? $issue['created_at'])) ?></td>
                                <td>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="issue_id" value="<?= $issue['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Opravdu chcete smazat tento technický problém?')">
                                            <i class="fas fa-trash"></i> Smazat
                                        </button>
                                    </form>
                                </td>
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
            // Zachování hodnot filtrů při automatickém obnovení
            const filterForm = document.getElementById('filterForm');
            const currentUrl = new URL(window.location.href);
            
            // Přidání všech parametrů z URL do formuláře
            for (const [key, value] of currentUrl.searchParams) {
                const input = filterForm.querySelector(`[name="${key}"]`);
                if (input) {
                    input.value = value;
                }
            }

            document.querySelectorAll('.status-badge').forEach(function(badge) {
                badge.addEventListener('click', function() {
                    const form = badge.closest('.status-form');
                    const select = form.querySelector('.status-select');
                    badge.classList.add('d-none');
                    select.classList.remove('d-none');
                    select.focus();
                });
            });
            document.querySelectorAll('.status-select').forEach(function(select) {
                select.addEventListener('blur', function() {
                    setTimeout(() => {
                        select.classList.add('d-none');
                        select.closest('.status-form').querySelector('.status-badge').classList.remove('d-none');
                    }, 200);
                });
                select.addEventListener('change', function() {
                    select.closest('form').submit();
                });
            });
        });
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>