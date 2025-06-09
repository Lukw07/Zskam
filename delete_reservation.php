<?php
require_once 'auth.php';
require_once 'db.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

redirect_if_not_logged_in();

// Načtení konfigurace
$mail_config = require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reservation_id'])) {
    $reservation_id = intval($_POST['reservation_id']);
    
    // Získání informací o rezervaci a uživateli
    $stmt = $conn->prepare("SELECT r.*, d.device_name, u.email, u.name, h.start_time, h.end_time 
                           FROM reservations r
                           JOIN devices d ON r.device_id = d.id
                           JOIN users u ON r.user_id = u.id
                           JOIN hours h ON r.hour = h.hour_number
                           WHERE r.id = ?");
    $stmt->bind_param("i", $reservation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $reservation = $result->fetch_assoc();
        
        // Uživatel může smazat pouze své vlastní rezervace nebo admin může smazat všechny
        if (is_admin() || $reservation['user_id'] == $_SESSION['user_id']) {
            $stmt = $conn->prepare("DELETE FROM reservations WHERE id = ?");
            $stmt->bind_param("i", $reservation_id);
            
            if ($stmt->execute()) {
                // Odeslání emailu pouze pokud rezervaci zrušil admin
                if (is_admin() && $reservation['user_id'] != $_SESSION['user_id']) {
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
                        $mail->addAddress($reservation['email']);
                    
                        // Obsah
                        $mail->isHTML(true);
                        $mail->Subject = "❌ Rezervace byla zrušena";
                        
                        $message = "
                        <!DOCTYPE html>
                        <html lang='cs'>
                        <head>
                            <meta charset='UTF-8'>
                            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                            <title>Rezervace zrušena</title>
                            <style>
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
                                .content {
                                    padding: 24px;
                                }
                                .warning-box {
                                    background: #fef2f2;
                                    border: 1px solid #ef4444;
                                    border-radius: 6px;
                                    padding: 16px;
                                    margin-bottom: 24px;
                                }
                                .warning-title {
                                    color: #dc2626;
                                    font-weight: 600;
                                    margin-bottom: 8px;
                                }
                                .info-box {
                                    background: #f8fafc;
                                    border-radius: 6px;
                                    padding: 16px;
                                    margin-bottom: 16px;
                                }
                                .info-label {
                                    font-size: 13px;
                                    color: #6b7280;
                                    text-transform: uppercase;
                                    letter-spacing: 0.05em;
                                    margin-bottom: 4px;
                                }
                                .info-value {
                                    font-size: 15px;
                                    font-weight: 600;
                                    color: #1f2937;
                                }
                                .footer {
                                    background: #1f2937;
                                    color: #9ca3af;
                                    padding: 24px;
                                    text-align: center;
                                }
                            </style>
                        </head>
                        <body>
                            <div class='email-container'>
                                <div class='header'>
                                    <h1>Rezervace zrušena</h1>
                                </div>
                                
                                <div class='content'>
                                    <div class='warning-box'>
                                        <div class='warning-title'>⚠️ Vaše rezervace byla zrušena</div>
                                        <p>Administrátor zrušil vaši rezervaci. Podrobnosti naleznete níže.</p>
                                    </div>
                                    
                                    <div class='info-box'>
                                        <div class='info-label'>Zařízení</div>
                                        <div class='info-value'>{$reservation['device_name']}</div>
                                    </div>
                                    
                                    <div class='info-box'>
                                        <div class='info-label'>Datum a čas</div>
                                        <div class='info-value'>" . date('d.m.Y', strtotime($reservation['date'])) . " - " . 
                                        date('H:i', strtotime($reservation['start_time'])) . " - " . 
                                        date('H:i', strtotime($reservation['end_time'])) . "</div>
                                    </div>
                                    
                                    <div class='info-box'>
                                        <div class='info-label'>Počet kusů</div>
                                        <div class='info-value'>{$reservation['quantity']} ks</div>
                                    </div>
                                </div>
                                
                                <div class='footer'>
                                    <div>Rezervační systém ZŠ Kamenická</div>
                                    <div style='margin-top: 8px; font-size: 12px;'>
                                        Automaticky generovaná zpráva • " . date('d.m.Y H:i:s') . "
                                    </div>
                                </div>
                            </div>
                        </body>
                        </html>
                        ";
                    
                        $mail->Body = $message;
                        
                        // Alternativní textová verze
                        $alt_body = "
                        Rezervační systém ZŠ Kamenická - Rezervace zrušena
                        ==========================================
                        
                        Vážený/á {$reservation['name']},
                        
                        vaše rezervace byla zrušena administrátorem.
                        
                        DETAILY REZERVACE
                        ================
                        Zařízení: {$reservation['device_name']}
                        Datum: " . date('d.m.Y', strtotime($reservation['date'])) . "
                        Čas: " . date('H:i', strtotime($reservation['start_time'])) . " - " . date('H:i', strtotime($reservation['end_time'])) . "
                        Počet kusů: {$reservation['quantity']} ks
                        
                        ==========================================
                        Rezervační systém ZŠ Kamenická
                        ";
                        
                        $mail->AltBody = $alt_body;
                    
                        $mail->send();
                    } catch (Exception $e) {
                        // I když se email nepodaří odeslat, pokračujeme
                    }
                }
                
                $_SESSION['success'] = "Rezervace byla úspěšně smazána";
            } else {
                $_SESSION['error'] = "Chyba při mazání rezervace";
            }
        } else {
            $_SESSION['error'] = "Nemáte oprávnění smazat tuto rezervaci";
        }
    } else {
        $_SESSION['error'] = "Rezervace nebyla nalezena";
    }
}

// Přesměrování zpět na dashboard
header("Location: dashboard.php");
exit(); 