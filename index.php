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
                        max-width: 150px;
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
                        padding: 1.5rem;
                        background-color: var(--light-gray);
                        border-bottom-left-radius: 16px;
                        border-bottom-right-radius: 16px;
                        text-align: center;
                        border-top: 1px solid var(--border-color);
                    }
                    
                    .footer-content {
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        gap: 0.75rem;
                    }
                    
                    .footer-content img {
                        height: 30px;
                        width: auto;
                        object-fit: contain;
                    }
                    
                    .footer-content div {
                        color: var(--dark-gray);
                        font-size: 0.85rem;
                        font-weight: 500;
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
                            <img src='https://zskamenicka.cz/wp-content/uploads/2025/06/zskam.png' alt='Logo školy' class='logo'>
                            <div class='company-info'>
                                <h1>IT Support</h1>
                                <p>ZŠ Kamenická</p>
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
                            <img src='https://zskamenicka.cz/wp-content/uploads/2025/06/logo_bezpozadi_white.webp' alt='Rezervo Logo' style='height: 40px; display: block; margin: 0 auto;'>
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
    <link rel="icon" type="image/png" href="logo1.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #3498db;
            --light-gray: #f0f2f5;
            --dark-gray: #6c757d;
            --text-color: #2c3e50;
            --white: #ffffff;
            --border-color: #e0e0e0;
            --shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: var(--light-gray);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 1rem;
        }

        .form-main-container {
            width: 100%;
            max-width: 550px;
            background: var(--white);
            border-radius: 16px;
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
        }

        .form-switcher {
            display: flex;
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .switcher-button {
            flex: 1;
            text-align: center;
            padding: 10px;
            text-decoration: none;
            color: var(--dark-gray);
            font-weight: 600;
            border-radius: 10px;
            transition: all 0.3s ease;
            position: relative;
        }

        .switcher-button i {
            margin-right: 8px;
        }
        
        .switcher-button:not(.active):hover {
            background-color: var(--light-gray);
        }
        
        .switcher-button.active {
            color: var(--primary-color);
            background-color: #eaf5fc;
        }

        .form-content {
            padding: 2rem;
        }
        
        .form-wrapper {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        .form-wrapper.hidden {
            display: none;
        }

        .form-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-color);
            text-align: center;
            margin-bottom: 0.5rem;
        }

        .form-subtitle {
            font-size: 1rem;
            color: var(--dark-gray);
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .form-control, .form-select {
            padding: 0.8rem 1rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.15);
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 0.8rem;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }

        .footer {
            padding: 1rem 1.5rem;
            background-color: var(--light-gray);
            border-top: 1px solid var(--border-color);
            border-bottom-left-radius: 16px;
            border-bottom-right-radius: 16px;
        }

        .footer-content {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }

        .footer-content img {
            height: 30px;
        }

        .footer-content div {
            font-size: 0.9rem;
            color: var(--dark-gray);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <?php
    // Určí, který formulář má být aktivní po odeslání
    $active_form = 'login';
    if (isset($_POST['action']) && $_POST['action'] === 'report_issue') {
        $active_form = 'issue';
    }
    ?>

    <div class="form-main-container">
        <div class="form-switcher">
            <a href="#" class="switcher-button <?php if ($active_form === 'login') echo 'active'; ?>" data-form="login">
                <i class="fas fa-user-circle"></i> Přihlášení do systému
            </a>
            <a href="#" class="switcher-button <?php if ($active_form === 'issue') echo 'active'; ?>" data-form="issue">
                <i class="fas fa-exclamation-triangle"></i> Nahlásit problém
            </a>
        </div>

        <div class="form-content">
            <!-- Přihlašovací formulář -->
            <form id="login-form" method="post" class="form-wrapper <?php if ($active_form !== 'login') echo 'hidden'; ?>">
                <h2 class="form-title">Rezervační systém</h2>
                <p class="form-subtitle">Přihlaste se ke svému účtu</p>

                <?php if (isset($login_error)): ?>
                    <div class="alert alert-danger p-2 text-center mb-3">
                        <?= $login_error ?>
                    </div>
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="mb-4">
                    <label class="form-label">Heslo</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Přihlásit</button>
            </form>

            <!-- Formulář pro nahlášení problému -->
            <form id="issue-form" method="post" class="form-wrapper <?php if ($active_form !== 'issue') echo 'hidden'; ?>">
                 <input type="hidden" name="action" value="report_issue">
                <h2 class="form-title">Máte potíže?</h2>
                <p class="form-subtitle">Dejte nám vědět, co se děje</p>

                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success p-2 text-center mb-3"><?= $success_message ?></div>
                <?php endif; ?>
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger p-2 text-center mb-3"><?= $error_message ?></div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nahlásil</label>
                        <select name="user_id" class="form-select" required>
                            <option value="">Vyberte uživatele</option>
                            <?php 
                            $users->data_seek(0);
                            while ($user = $users->fetch_assoc()): 
                            ?>
                                <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Třída</label>
                        <input type="text" name="class" class="form-control" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Popis problému</label>
                    <textarea name="description" class="form-control" rows="3" required></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Naléhavost</label>
                    <select name="urgency" class="form-select" required>
                        <option value="nízká">Nízká</option>
                        <option value="střední">Střední</option>
                        <option value="vysoká">Vysoká</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary w-100">Odeslat hlášení</button>
            </form>
        </div>

        <?php include 'footer.php'; ?>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const switcherButtons = document.querySelectorAll('.switcher-button');
        const forms = document.querySelectorAll('.form-wrapper');

        switcherButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const targetFormId = this.dataset.form + '-form';
                
                // Switch active button
                switcherButtons.forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                
                // Show target form
                forms.forEach(form => {
                    if (form.id === targetFormId) {
                        form.classList.remove('hidden');
                    } else {
                        form.classList.add('hidden');
                    }
                });
            });
        });
    });
    </script>

</body>
</html>