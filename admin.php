<?php
require_once 'auth.php';
require_once 'db.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

redirect_if_not_logged_in();
if (!is_admin()) die("Přístup odepřen");

// Načtení konfigurace
$mail_config = require 'config.php';

// Správa uživatelů
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'create':
            $name = $conn->real_escape_string($_POST['name']);
            $email = $conn->real_escape_string($_POST['email']);
            $password = $_POST['password'];
            $role = $_POST['role'] === 'admin' ? 'admin' : 'user';
            
            $stmt = $conn->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)");
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt->bind_param("ssss", $name, $email, $password_hash, $role);
            
            if ($stmt->execute()) {
                // Odeslání emailu s přihlašovacími údaji
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
                    $mail->addAddress($email, $name);
                
                    // Obsah
                    $mail->isHTML(true);
                    $mail->Subject = "Přístupové údaje - Rezervační systém ZŠ Kamenická";
                    
                    $message = "
                    <!DOCTYPE html>
                    <html lang='cs'>
                    <head>
                        <meta charset='UTF-8'>
                        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                        <title>Přístupové údaje</title>
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
                            
                            .content {
                                padding: 24px;
                            }
                            
                            .welcome-section {
                                margin-bottom: 24px;
                            }
                            
                            .welcome-text {
                                font-size: 16px;
                                color: #374151;
                                margin-bottom: 16px;
                            }
                            
                            .credentials-box {
                                background: #f8fafc;
                                border: 1px solid #e5e7eb;
                                border-radius: 6px;
                                padding: 16px;
                                margin-bottom: 24px;
                            }
                            
                            .credentials-title {
                                font-size: 14px;
                                font-weight: 600;
                                color: #6b7280;
                                text-transform: uppercase;
                                letter-spacing: 0.05em;
                                margin-bottom: 12px;
                            }
                            
                            .credential-item {
                                margin-bottom: 12px;
                            }
                            
                            .credential-label {
                                font-size: 13px;
                                color: #6b7280;
                                margin-bottom: 4px;
                            }
                            
                            .credential-value {
                                font-size: 15px;
                                font-weight: 600;
                                color: #111827;
                                background: #ffffff;
                                padding: 8px 12px;
                                border-radius: 4px;
                                border: 1px solid #e5e7eb;
                            }
                            
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
                        </style>
                    </head>
                    <body>
                        <div class='email-container'>
                            <div class='header'>
                                <div class='logo-section'>
                                    <img src='https://zskamenicka.cz/wp-content/uploads/2024/11/z-kamenick-dn-ii-high-resolution-logo-transparent.png' alt='Logo školy' class='logo'>
                                    <div class='company-info'>
                                        <h1>Rezervační systém</h1>
                                        <p>ZŠ Kamenická</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class='content'>
                                <div class='welcome-section'>
                                    <div class='welcome-text'>
                                        Vážený/á {$name},<br><br>
                                        byl vám vytvořen účet v rezervačním systému ZŠ Kamenická. Níže naleznete vaše přihlašovací údaje.
                                    </div>
                                </div>
                                
                                <div class='credentials-box'>
                                    <div class='credentials-title'>Přístupové údaje</div>
                                    <div class='credential-item'>
                                        <div class='credential-label'>Email</div>
                                        <div class='credential-value'>{$email}</div>
                                    </div>
                                    <div class='credential-item'>
                                        <div class='credential-label'>Heslo</div>
                                        <div class='credential-value'>{$password}</div>
                                    </div>
                                </div>
                                
                                <div class='welcome-text'>
                                    Pro přihlášení použijte tyto údaje na adrese:<br>
                                    <a href='https://it.zskamenicka.cz' style='color: #2563eb;'>https://it.zskamenicka.cz</a>
                                </div>
                            </div>
                            
                            <div class='footer'>
                                <div class='footer-content'>
                                    <div class='footer-title'>Rezervační systém ZŠ Kamenická</div>
                                    <div>Automaticky generovaná zpráva • " . date('d.m.Y H:i:s') . "</div>
                                    <div style='margin-top: 12px; font-size: 12px;'>
                                        Systém pro správu rezervací a technických problémů
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
                    Rezervační systém ZŠ Kamenická - Přístupové údaje
                    ==========================================
                    
                    Vážený/á {$name},
                    
                    byl vám vytvořen účet v rezervačním systému ZŠ Kamenická.
                    
                    PŘÍSTUPOVÉ ÚDAJE
                    ===============
                    Email: {$email}
                    Heslo: {$password}
                    
                    Pro přihlášení navštivte: https://it.zskamenicka.cz
                    
                    ==========================================
                    Rezervační systém ZŠ Kamenická
                    ";
                    
                    $mail->AltBody = $alt_body;
                
                    $mail->send();
                    $success_message = "✅ Uživatel byl úspěšně vytvořen a odeslán email s přihlašovacími údaji";
                    
                } catch (Exception $e) {
                    $error_message = "❌ Chyba při odesílání emailu: {$mail->ErrorInfo}";
                }
            } else {
                $error_message = "❌ Chyba při vytváření uživatele";
            }
            break;
            
        case 'update':
            $id = intval($_POST['id']);
            $name = $conn->real_escape_string($_POST['name']);
            $email = $conn->real_escape_string($_POST['email']);
            $role = $_POST['role'] === 'admin' ? 'admin' : 'user';
            
            // Získání původních údajů pro porovnání
            $stmt = $conn->prepare("SELECT name, email, role FROM users WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $old_data = $stmt->get_result()->fetch_assoc();
            
            if (!empty($_POST['password'])) {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, password_hash = ?, role = ? WHERE id = ?");
                $stmt->bind_param("ssssi", $name, $email, $password, $role, $id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?");
                $stmt->bind_param("sssi", $name, $email, $role, $id);
            }
            
            if ($stmt->execute()) {
                // Odeslání emailu o změně údajů
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
                    $mail->addAddress($email, $name);
                
                    // Obsah
                    $mail->isHTML(true);
                    $mail->Subject = "Změna údajů - Rezervační systém ZŠ Kamenická";
                    
                    $changes = [];
                    if ($old_data['name'] !== $name) $changes[] = "Jméno: {$old_data['name']} → {$name}";
                    if ($old_data['email'] !== $email) $changes[] = "Email: {$old_data['email']} → {$email}";
                    if ($old_data['role'] !== $role) $changes[] = "Role: {$old_data['role']} → {$role}";
                    if (!empty($_POST['password'])) $changes[] = "Heslo bylo změněno";
                    
                    $message = "
                    <!DOCTYPE html>
                    <html lang='cs'>
                    <head>
                        <meta charset='UTF-8'>
                        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                        <title>Změna údajů</title>
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
                            
                            .content {
                                padding: 24px;
                            }
                            
                            .welcome-section {
                                margin-bottom: 24px;
                            }
                            
                            .welcome-text {
                                font-size: 16px;
                                color: #374151;
                                margin-bottom: 16px;
                            }
                            
                            .changes-box {
                                background: #f8fafc;
                                border: 1px solid #e5e7eb;
                                border-radius: 6px;
                                padding: 16px;
                                margin-bottom: 24px;
                            }
                            
                            .changes-title {
                                font-size: 14px;
                                font-weight: 600;
                                color: #6b7280;
                                text-transform: uppercase;
                                letter-spacing: 0.05em;
                                margin-bottom: 12px;
                            }
                            
                            .change-item {
                                margin-bottom: 8px;
                                padding: 8px;
                                background: #ffffff;
                                border-radius: 4px;
                                border: 1px solid #e5e7eb;
                            }
                            
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
                        </style>
                    </head>
                    <body>
                        <div class='email-container'>
                            <div class='header'>
                                <div class='logo-section'>
                                    <img src='https://zskamenicka.cz/wp-content/uploads/2024/11/z-kamenick-dn-ii-high-resolution-logo-transparent.png' alt='Logo školy' class='logo'>
                                    <div class='company-info'>
                                        <h1>Rezervační systém</h1>
                                        <p>ZŠ Kamenická</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class='content'>
                                <div class='welcome-section'>
                                    <div class='welcome-text'>
                                        Vážený/á {$name},<br><br>
                                        byly změněny údaje vašeho účtu v rezervačním systému ZŠ Kamenická.
                                    </div>
                                </div>
                                
                                <div class='changes-box'>
                                    <div class='changes-title'>Provedené změny</div>
                                    " . implode('', array_map(function($change) {
                                        return "<div class='change-item'>{$change}</div>";
                                    }, $changes)) . "
                                </div>
                                
                                <div class='welcome-text'>
                                    Pro přihlášení použijte své nové údaje na adrese:<br>
                                    <a href='https://it.zskamenicka.cz' style='color: #2563eb;'>https://it.zskamenicka.cz</a>
                                </div>
                            </div>
                            
                            <div class='footer'>
                                <div class='footer-content'>
                                    <div class='footer-title'>Rezervační systém ZŠ Kamenická</div>
                                    <div>Automaticky generovaná zpráva • " . date('d.m.Y H:i:s') . "</div>
                                    <div style='margin-top: 12px; font-size: 12px;'>
                                        Systém pro správu rezervací a technických problémů
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
                    Rezervační systém ZŠ Kamenická - Změna údajů
                    ==========================================
                    
                    Vážený/á {$name},
                    
                    byly změněny údaje vašeho účtu v rezervačním systému ZŠ Kamenická.
                    
                    PROVEDENÉ ZMĚNY
                    ===============
                    " . implode("\n", $changes) . "
                    
                    Pro přihlášení použijte své nové údaje na adrese: https://it.zskamenicka.cz
                    
                    ==========================================
                    Rezervační systém ZŠ Kamenická
                    ";
                    
                    $mail->AltBody = $alt_body;
                
                    $mail->send();
                    $success_message = "✅ Údaje byly úspěšně aktualizovány a odeslán email s notifikací";
                    
                } catch (Exception $e) {
                    $error_message = "❌ Chyba při odesílání emailu: {$mail->ErrorInfo}";
                }
            }
            break;
            
        case 'delete':
            $id = intval($_POST['id']);
            
            // Získání údajů uživatele před smazáním
            $stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $user_data = $stmt->get_result()->fetch_assoc();
            
            if ($user_data) {
                // Odeslání emailu o smazání účtu
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
                    $mail->addAddress($user_data['email'], $user_data['name']);
                
                    // Obsah
                    $mail->isHTML(true);
                    $mail->Subject = "Smazání účtu - Rezervační systém ZŠ Kamenická";
                    
                    $message = "
                    <!DOCTYPE html>
                    <html lang='cs'>
                    <head>
                        <meta charset='UTF-8'>
                        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                        <title>Smazání účtu</title>
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
                            
                            .content {
                                padding: 24px;
                            }
                            
                            .welcome-section {
                                margin-bottom: 24px;
                            }
                            
                            .welcome-text {
                                font-size: 16px;
                                color: #374151;
                                margin-bottom: 16px;
                            }
                            
                            .warning-box {
                                background: #fef2f2;
                                border: 1px solid #fee2e2;
                                border-radius: 6px;
                                padding: 16px;
                                margin-bottom: 24px;
                            }
                            
                            .warning-title {
                                font-size: 14px;
                                font-weight: 600;
                                color: #dc2626;
                                text-transform: uppercase;
                                letter-spacing: 0.05em;
                                margin-bottom: 12px;
                            }
                            
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
                        </style>
                    </head>
                    <body>
                        <div class='email-container'>
                            <div class='header'>
                                <div class='logo-section'>
                                    <img src='https://zskamenicka.cz/wp-content/uploads/2024/11/z-kamenick-dn-ii-high-resolution-logo-transparent.png' alt='Logo školy' class='logo'>
                                    <div class='company-info'>
                                        <h1>Rezervační systém</h1>
                                        <p>ZŠ Kamenická</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class='content'>
                                <div class='welcome-section'>
                                    <div class='welcome-text'>
                                        Vážený/á {$user_data['name']},<br><br>
                                        váš účet v rezervačním systému ZŠ Kamenická byl smazán.
                                    </div>
                                </div>
                                
                                <div class='warning-box'>
                                    <div class='warning-title'>Důležité upozornění</div>
                                    <p>Váš účet byl smazán z databáze. Pokud se domníváte, že k tomu došlo omylem, kontaktujte prosím administrátora systému.</p>
                                </div>
                            </div>
                            
                            <div class='footer'>
                                <div class='footer-content'>
                                    <div class='footer-title'>Rezervační systém ZŠ Kamenická</div>
                                    <div>Automaticky generovaná zpráva • " . date('d.m.Y H:i:s') . "</div>
                                    <div style='margin-top: 12px; font-size: 12px;'>
                                        Systém pro správu rezervací a technických problémů
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
                    Rezervační systém ZŠ Kamenická - Smazání účtu
                    ==========================================
                    
                    Vážený/á {$user_data['name']},
                    
                    váš účet v rezervačním systému ZŠ Kamenická byl smazán.
                    
                    DŮLEŽITÉ UPOZORNĚNÍ
                    ==================
                    Váš účet byl smazán z databáze. Pokud se domníváte, že k tomu došlo omylem, 
                    kontaktujte prosím administrátora systému.
                    
                    ==========================================
                    Rezervační systém ZŠ Kamenická
                    ";
                    
                    $mail->AltBody = $alt_body;
                
                    $mail->send();
                } catch (Exception $e) {
                    // I když se email nepodaří odeslat, pokračujeme ve smazání účtu
                }
            }
            
            $conn->query("DELETE FROM users WHERE id = $id");
            $success_message = "✅ Uživatel byl úspěšně smazán";
            break;
    }
}

$users = $conn->query("SELECT * FROM users");
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <style>
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

            /* Úprava formuláře pro nového uživatele */
            .row.g-3 {
                margin-bottom: 0.5rem;
            }

            .col-md-4, .col-md-2 {
                margin-bottom: 0.5rem;
            }

            /* Úprava tabulky */
            .table-responsive {
                margin: 0 -10px;
                padding: 0 10px;
                width: calc(100% + 20px);
            }

            .table {
                font-size: 0.9rem;
            }

            .table td, .table th {
                padding: 0.5rem;
                white-space: nowrap;
            }

            /* Úprava formulářových prvků v tabulce */
            .form-control-sm, .form-select-sm {
                font-size: 0.9rem;
                padding: 0.3rem 0.5rem;
                margin-bottom: 0.3rem;
            }

            /* Úprava tlačítek */
            .btn-sm {
                padding: 0.3rem 0.5rem;
                font-size: 0.9rem;
                margin-bottom: 0.3rem;
                display: block;
                width: 100%;
            }

            /* Úprava nadpisů */
            h2 {
                font-size: 1.5rem;
                margin-bottom: 1rem;
            }

            h4 {
                font-size: 1.2rem;
                margin-bottom: 0.75rem;
            }

            /* Úprava alertů */
            .alert {
                padding: 0.75rem;
                margin-bottom: 1rem;
                font-size: 0.9rem;
            }

            /* Úprava input group */
            .input-group-text {
                font-size: 0.9rem;
                padding: 0.3rem 0.5rem;
            }

            /* Úprava form-group */
            .form-group {
                margin-bottom: 0.5rem;
            }

            /* Úprava tlačítek v tabulce */
            .d-inline {
                display: block !important;
                margin-top: 0.5rem;
            }

            /* Úprava buněk v tabulce */
            td {
                vertical-align: middle !important;
            }

            /* Úprava formuláře v tabulce */
            form {
                margin-bottom: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <h2>Správa uživatelů</h2>
        
        <div class="mb-4">
            <h4>Nový uživatel</h4>
            <form method="post" class="row g-3">
                <input type="hidden" name="action" value="create">
                <div class="col-md-4">
                    <input type="text" name="name" class="form-control" placeholder="Jméno" required>
                </div>
                <div class="col-md-4">
                    <input type="email" name="email" class="form-control" placeholder="Email" required>
                </div>
                <div class="col-md-2">
                    <input type="password" name="password" class="form-control" placeholder="Heslo" required>
                </div>
                <div class="col-md-2">
                    <select name="role" class="form-select">
                        <option value="user">Uživatel</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-success">Vytvořit</button>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Jméno</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Akce</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($user = $users->fetch_assoc()): ?>
                    <tr>
                        <td><?= $user['id'] ?></td>
                        <td>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                <input type="text" name="name" class="form-control form-control-sm" value="<?= htmlspecialchars($user['name']) ?>" required>
                        </td>
                        <td>
                                <input type="email" name="email" class="form-control form-control-sm" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </td>
                        <td>
                                <select name="role" class="form-select form-select-sm">
                                    <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>Uživatel</option>
                                    <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                </select>
                        </td>
                        <td>
                                <input type="password" name="password" class="form-control form-control-sm" placeholder="Nové heslo (volitelné)">
                                <button type="submit" class="btn btn-primary btn-sm mt-1">Uložit</button>
                            </form>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Opravdu chcete smazat tohoto uživatele?')">Smazat</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="auto_refresh.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            setupAutoRefresh('admin-content', 'admin.php', 60000);
        });
    </script>
</body>
</html>