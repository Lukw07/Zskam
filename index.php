<?php
require_once 'db.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

session_start();

// Načtení konfigurace
$mail_config = require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'report_issue') {
        $class = $conn->real_escape_string($_POST['class']);
        $description = $conn->real_escape_string($_POST['description']);
        $urgency = $conn->real_escape_string($_POST['urgency']);
        $user_id = intval($_POST['user_id']);
        
        // Uložení problému do databáze
        $stmt = $conn->prepare("INSERT INTO technical_issues (user_id, class, description, urgency, status) VALUES (?, ?, ?, ?, 'nový')");
        $stmt->bind_param("isss", $user_id, $class, $description, $urgency);
        $stmt->execute();
        
        // Získání ID nově vytvořeného incidentu
        $issue_id = $conn->insert_id;
        
        // Získání jména uživatele pro email
        $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user_name = $stmt->get_result()->fetch_assoc()['name'];
        
        // Získání všech administrátorů
        $admins = $conn->query("SELECT email FROM users WHERE role = 'admin'");
        
        // Odeslání emailu
        $mail = new PHPMailer(true);
        try {
            // Nastavení serveru
            $mail->isSMTP();
            $mail->Host = $mail_config['smtp']['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $mail_config['smtp']['username'];
            $mail->Password = $mail_config['smtp']['password'];
            $mail->SMTPSecure = $mail_config['smtp']['encryption'];
            $mail->Port = $mail_config['smtp']['port'];
            $mail->CharSet = $mail_config['smtp']['charset'];

            // Příjemci
            $mail->setFrom($mail_config['smtp']['from_email'], $mail_config['smtp']['from_name']);
            while ($admin = $admins->fetch_assoc()) {
                $mail->addAddress($admin['email']);
            }

            // Obsah
            $mail->isHTML(true);
            $mail->Subject = "⚠️ IT Incident - {$class} | {$urgency}";
            
            // Nastavení barev a ikon podle naléhavosti
            $urgency_settings = [
                'Nízká' => [
                    'color' => '#10b981',
                    'bg_color' => '#ecfdf5',
                    'border_color' => '#10b981',
                    'icon' => '🟢',
                    'status' => 'LOW'
                ],
                'Střední' => [
                    'color' => '#f59e0b',
                    'bg_color' => '#fffbeb',
                    'border_color' => '#f59e0b',
                    'icon' => '🟡',
                    'status' => 'MEDIUM'
                ],
                'Vysoká' => [
                    'color' => '#f97316',
                    'bg_color' => '#fff7ed',
                    'border_color' => '#f97316',
                    'icon' => '🟠',
                    'status' => 'HIGH'
                ],
                'Kritická' => [
                    'color' => '#ef4444',
                    'bg_color' => '#fef2f2',
                    'border_color' => '#ef4444',
                    'icon' => '🔴',
                    'status' => 'CRITICAL'
                ]
            ];
            
            $current_urgency = $urgency_settings[$urgency] ?? $urgency_settings['Nízká'];
            $current_time = date('d.m.Y H:i:s');
            
            // Použití skutečného ID incidentu s lepším formátováním
            $incident_id = sprintf('INC-%06d', $issue_id);
            
            // URL pro označení jako přečteno s ID incidentu
            $mark_read_url = "http://localhost/zskam/mark_read.php?id=" . $issue_id;
            
            $message = "
            <!DOCTYPE html>
            <html lang='cs'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>IT Support Incident</title>
                <style>
                    * {
                        margin: 0;
                        padding: 0;
                        box-sizing: border-box;
                    }
                    
                    body {
                        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                        line-height: 1.6;
                        color: #1f2937;
                        background-color: #f8fafc;
                    }
                    
                    .email-container {
                        max-width: 600px;
                        margin: 20px auto;
                        background: #ffffff;
                        border-radius: 8px;
                        overflow: hidden;
                        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
                        border: 1px solid #e5e7eb;
                    }
                    
                    /* Header s logem */
                    .header {
                        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
                        color: white;
                        padding: 24px;
                        text-align: center;
                    }
                    
                    .logo-section {
                        width: 100%;
                        margin-bottom: 16px;
                    }
                    
                    .logo {
                        width: 100%;
                        max-width: 300px;
                        height: auto;
                        margin: 0 auto;
                        display: block;
                    }
                    
                    .company-info {
                        margin-top: 16px;
                    }
                    
                    .company-info h1 {
                        font-size: 20px;
                        font-weight: 600;
                        margin-bottom: 4px;
                    }
                    
                    .company-info p {
                        font-size: 14px;
                        opacity: 0.9;
                    }
                    
                    /* Urgency Banner */
                    .urgency-banner {
                        background: #ffffff;
                        border-left: 4px solid #2563eb;
                        padding: 16px;
                        display: flex;
                        flex-direction: column;
                        gap: 16px;
                    }
                    
                    .urgency-info {
                        display: flex;
                        align-items: center;
                        gap: 12px;
                    }
                    
                    .urgency-badge {
                        display: none;
                    }
                    
                    .urgency-text {
                        font-size: 16px;
                        font-weight: 600;
                        color: #111827;
                    }
                    
                    .incident-id {
                        background: #1e293b;
                        color: white;
                        padding: 8px 16px;
                        border-radius: 4px;
                        font-family: 'Courier New', monospace;
                        font-size: 14px;
                        font-weight: 600;
                        display: inline-block;
                        margin-top: 8px;
                    }
                    
                    /* Content */
                    .content {
                        padding: 24px;
                    }
                    
                    .section {
                        margin-bottom: 24px;
                    }
                    
                    .section-title {
                        font-size: 16px;
                        font-weight: 600;
                        color: #1f2937;
                        margin-bottom: 16px;
                        padding-bottom: 8px;
                        border-bottom: 1px solid #e5e7eb;
                    }
                    
                    /* Info Grid */
                    .info-grid {
                        display: grid;
                        grid-template-columns: 1fr;
                        gap: 16px;
                        margin-bottom: 24px;
                    }
                    
                    .info-item {
                        background: #f8fafc;
                        padding: 16px;
                        border-radius: 6px;
                        border-left: 3px solid #3b82f6;
                    }
                    
                    .info-label {
                        font-size: 13px;
                        font-weight: 600;
                        color: #6b7280;
                        text-transform: uppercase;
                        letter-spacing: 0.05em;
                        margin-bottom: 4px;
                    }
                    
                    .info-value {
                        font-size: 15px;
                        color: #111827;
                        font-weight: 500;
                    }
                    
                    /* Description Box */
                    .description-box {
                        background: #ffffff;
                        border: 1px solid #e5e7eb;
                        border-radius: 6px;
                        padding: 16px;
                        position: relative;
                    }
                    
                    .description-box::before {
                        content: 'Popis problému';
                        position: absolute;
                        top: -10px;
                        left: 16px;
                        background: white;
                        padding: 0 8px;
                        font-size: 13px;
                        font-weight: 600;
                        color: #6b7280;
                        text-transform: uppercase;
                    }
                    
                    .description-content {
                        font-size: 14px;
                        line-height: 1.6;
                        color: #374151;
                    }
                    
                    /* Reporter Info */
                    .reporter-section {
                        background: #f8fafc;
                        border-radius: 6px;
                        padding: 16px;
                        border: 1px solid #e5e7eb;
                    }
                    
                    .reporter-info {
                        display: flex;
                        align-items: center;
                        gap: 12px;
                    }
                    
                    .reporter-details h3 {
                        font-size: 15px;
                        font-weight: 600;
                        color: #1f2937;
                        margin-bottom: 4px;
                    }
                    
                    .reporter-details p {
                        font-size: 14px;
                        color: #6b7280;
                    }
                    
                    /* Footer */
                    .footer {
                        background: #1f2937;
                        color: #9ca3af;
                        padding: 24px;
                        text-align: center;
                    }
                    
                    .footer-content {
                        font-size: 13px;
                        line-height: 1.5;
                    }
                    
                    .footer-title {
                        color: white;
                        font-weight: 600;
                        margin-bottom: 8px;
                        font-size: 15px;
                    }
                    
                    .action-button {
                        display: inline-block;
                        background: #3b82f6;
                        color: white;
                        padding: 12px 24px;
                        border-radius: 6px;
                        text-decoration: none;
                        font-weight: 500;
                        margin-top: 16px;
                        transition: background-color 0.2s;
                    }
                    
                    .action-button:hover {
                        background: #2563eb;
                    }
                </style>
            </head>
            <body>
                <div class='email-container'>
                    <!-- Header -->
                    <div class='header'>
                        <div class='logo-section'>
                            <img src='https://zskamenicka.cz/wp-content/uploads/2024/11/z-kamenick-dn-ii-high-resolution-logo-transparent.png' alt='Logo školy' class='logo'>
                            <div class='company-info'>
                                <h1>Rezervo</h1>
                                <p>IT Support System</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Urgency Banner -->
                    <div class='urgency-banner' style='background: #f8fafc; border-left: 4px solid #2563eb; padding: 20px; margin-bottom: 20px;'>
                        <div class='incident-id' style='margin: 0; padding: 12px 20px; background: #1e293b; color: white; border-radius: 6px; font-family: monospace; font-size: 16px; letter-spacing: 1px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
                            Incident ID: {$incident_id}
                        </div>
                    </div>
                    
                    <!-- Content -->
                    <div class='content'>
                        <!-- Incident Details -->
                        <div class='section'>
                            <h2 class='section-title'>Detail incidentu</h2>
                            
                            <div class='info-grid'>
                                <div class='info-item'>
                                    <div class='info-label'>Třída</div>
                                    <div class='info-value'>{$class}</div>
                                </div>
                                <div class='info-item'>
                                    <div class='info-label'>Priorita</div>
                                    <div class='info-value' style='color: {$current_urgency['color']}'>{$urgency}</div>
                                </div>
                            </div>
                            
                            <div class='description-box'>
                                <div class='description-content'>
                                    {$description}
                                </div>
                            </div>
                        </div>
                        
                        <!-- Reporter Info -->
                        <div class='section'>
                            <h2 class='section-title'>Nahlásil uživatel</h2>
                            <div class='reporter-section'>
                                <div class='reporter-info'>
                                    <div class='reporter-details'>
                                        <h3>{$user_name}</h3>
                                        <p>Nahlášeno: {$current_time}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class='section' style='text-align: center;'>
                            <a href='{$mark_read_url}' class='action-button' style='display: inline-block; background: #3b82f6; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: 500; margin-top: 16px; transition: background-color 0.2s;'>
                                Označit jako přečteno
                            </a>
                        </div>
                    </div>
                    
                    <!-- Footer -->
                    <div class='footer'>
                        <div class='footer-content'>
                            <div class='footer-title'>Rezervo</div>
                            <div style='margin-top: 12px; font-size: 12px;'>
                                By Kryštof Tůma
                            </div>
                            <div style='margin-top: 8px; font-size: 11px; color: #6b7280;'>
                                IT systém pro správu incidentů a rezervací
                            </div>
                            <div style='margin-top: 4px; font-size: 10px; color: #9ca3af;'>
                                Tento email byl automaticky vygenerován dne {$current_time}
                            </div>
                        </div>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $mail->Body = $message;
            
            // Alternativní textová verze
            $alt_body = "
        IT Support System - Incident Notification
        ==========================================
        
        INCIDENT ID: {$incident_id}
        PRIORITA: {$urgency} ({$current_urgency['status']})
        ČAS: {$current_time}
        
        DETAIL INCIDENTU
        ================
        Kategorie: {$class}
        Naléhavost: {$urgency}
        
        Popis problému:
        {$description}
        
        NAHLÁSIL
        ========
        Uživatel: {$user_name}
        Čas nahlášení: {$current_time}
        
        OZNAČIT JAKO PŘEČTENO
        ====================
        {$mark_read_url}
        
        ==========================================
        Rezervo - Automatická notifikace
        By Kryštof Tůma
            ";
            
            $mail->AltBody = $alt_body;

            $mail->send();
            $success_message = "✅ Incident byl úspěšně nahlášen (ID: {$incident_id})";
            
        } catch (Exception $e) {
            $error_message = "❌ Chyba při nahlášení incidentu: {$mail->ErrorInfo}";
        }
    } else {
        $email = $conn->real_escape_string($_POST['email']);
        $password = $_POST['password'];

        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['name'] = $user['name'];
                header("Location: dashboard.php");
                exit();
            }
        }
        $login_error = "Neplatné přihlašovací údaje";
    }
}

// Získání seznamu uživatelů pro výběr
$users = $conn->query("SELECT id, name FROM users ORDER BY name");
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="60">
    <title>Rezervo - Přihlášení</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        .login-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .card-title {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }
        .form-control {
            border-radius: 10px;
            padding: 0.8rem 1rem;
            border: 1px solid #e0e0e0;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
            border-color: #3498db;
        }
        .btn-primary {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            border: none;
            border-radius: 10px;
            padding: 0.8rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }
        .alert {
            border-radius: 10px;
            border: none;
        }
        .form-label {
            font-weight: 500;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        .input-group-text {
            border-radius: 10px;
            background-color: #f8f9fa;
            border: 1px solid #e0e0e0;
        }
        .form-select {
            border-radius: 10px;
            padding: 0.8rem 1rem;
            border: 1px solid #e0e0e0;
        }
        .badge {
            padding: 0.5rem 1rem;
            border-radius: 8px;
        }
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responzivní styly */
        @media (max-width: 768px) {
            .login-container {
                margin: 1rem auto;
                padding: 0 0.5rem;
            }

            .card {
                margin-bottom: 1rem;
            }

            .card-body {
                padding: 1rem;
            }

            .card-title {
                font-size: 1.5rem;
                margin-bottom: 1rem;
            }

            .form-control, .form-select {
                font-size: 16px; /* Zabrání zoomování na iOS */
                padding: 0.6rem 0.8rem;
            }

            .form-label {
                font-size: 0.9rem;
                margin-bottom: 0.3rem;
            }

            .btn {
                padding: 0.6rem;
                font-size: 0.9rem;
            }

            .alert {
                padding: 0.75rem;
                margin-bottom: 1rem;
                font-size: 0.9rem;
            }

            .input-group-text {
                font-size: 0.9rem;
                padding: 0.6rem 0.8rem;
            }

            .badge {
                font-size: 0.8rem;
                padding: 0.4rem 0.6rem;
            }

            /* Úprava ikon */
            .fas {
                font-size: 1.2rem;
            }

            /* Úprava nadpisů */
            h2 {
                font-size: 1.5rem;
                margin-bottom: 1rem;
            }

            /* Úprava textarea */
            textarea.form-control {
                min-height: 100px;
            }

            /* Úprava selectu pro uživatele */
            select[name="user_id"] {
                font-size: 16px; /* Zabrání zoomování na iOS */
            }
        }
    </style>
</head>
<body>
    <div class="login-container fade-in">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-body p-4">
                        <h2 class="card-title text-center">
                            <i class="fas fa-user-circle mb-3 d-block" style="font-size: 3rem; color: #3498db;"></i>
                            Přihlášení
                        </h2>
                        <?php if (isset($login_error)): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?= $login_error ?>
                            </div>
                        <?php endif; ?>
                        <form method="post">
                            <div class="mb-4">
                                <label class="form-label">
                                    <i class="fas fa-envelope me-2"></i>Email
                                </label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-label">
                                    <i class="fas fa-lock me-2"></i>Heslo
                                </label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-sign-in-alt me-2"></i>Přihlásit
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body p-4">
                        <h2 class="card-title text-center">
                            <i class="fas fa-exclamation-triangle mb-3 d-block" style="font-size: 3rem; color: #e74c3c;"></i>
                            Nahlášení technického problému
                        </h2>
                        <?php if (isset($success_message)): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                <?= $success_message ?>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($error_message)): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?= $error_message ?>
                            </div>
                        <?php endif; ?>
                        <form method="post">
                            <input type="hidden" name="action" value="report_issue">
                            <div class="mb-4">
                                <label class="form-label">
                                    <i class="fas fa-user me-2"></i>Nahlásil
                                </label>
                                <select name="user_id" class="form-select" required>
                                    <option value="">Vyberte uživatele</option>
                                    <?php while ($user = $users->fetch_assoc()): ?>
                                        <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label class="form-label">
                                    <i class="fas fa-chalkboard me-2"></i>Třída
                                </label>
                                <input type="text" name="class" class="form-control" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-label">
                                    <i class="fas fa-comment-alt me-2"></i>Popis problému
                                </label>
                                <textarea name="description" class="form-control" rows="3" required></textarea>
                            </div>
                            <div class="mb-4">
                                <label class="form-label">
                                    <i class="fas fa-exclamation-circle me-2"></i>Naléhavost
                                </label>
                                <select name="urgency" class="form-select" required>
                                    <option value="nízká">Nízká</option>
                                    <option value="střední">Střední</option>
                                    <option value="vysoká">Vysoká</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-paper-plane me-2"></i>Nahlásit problém
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<footer class="footer mt-auto py-3 bg-light">
    <div class="container text-center">
        <span class="text-muted">Rezervo by Kryštof Tůma 2025</span>
    </div>
</footer>