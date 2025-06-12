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
                            <title>Zrušení rezervace</title>
                            <style>
                                body { font-family: sans-serif; line-height: 1.6; color: #333; }
                                .container { max-width: 600px; margin: 20px auto; border: 1px solid #ddd; border-radius: 8px; overflow: hidden; }
                                .header { background-color: #f8f8f8; padding: 20px; text-align: center; border-bottom: 1px solid #eee; }
                                .content { padding: 20px; }
                                .footer { background-color: #333; color: white; padding: 20px; text-align: center; font-size: 0.9em; }
                                .footer-title { font-weight: bold; margin-bottom: 5px; }
                                .logo { max-width: 150px; height: auto; margin-bottom: 15px; }
                            </style>
                        </head>
                        <body>
                            <div class='container'>
                                <div class='header'>
                                    <img src='https://zskamenicka.cz/wp-content/uploads/2025/06/zskam.webp' alt='Logo školy' class='logo'>
                                    <h2>Zrušení rezervace</h2>
                                </div>
                                <div class='content'>
                                    <p>Vážený uživateli,</p>
                                    <p>Vaše rezervace byla úspěšně zrušena.</p>
                                    <p>Detaily rezervace:</p>
                                    <ul>
                                        <li>Zařízení: {$reservation['device_name']}</li>
                                        <li>Datum: " . date('d.m.Y', strtotime($reservation['date'])) . "</li>
                                        <li>Čas: " . date('H:i', strtotime($reservation['start_time'])) . " - " . date('H:i', strtotime($reservation['end_time'])) . "</li>
                                    </ul>
                                </div>
                                <div class='footer'>
                                    <img src='https://zskamenicka.cz/wp-content/uploads/2025/06/logo_bezpozadi_white.webp' alt='Rezervo Logo' style='height: 40px; display: block; margin: 0 auto;'>
                                    <div style='margin-top: 5px;'>By Kryštof Tůma</div>
                                </div>
                            </div>
                        </body>
                        </html>
                        ";
                    
                        $mail->Body = $message;
                        
                        // Přidání loga jako přílohy (hlavička) - ODSTRANĚNO, POUŽITA URL
                        // $mail->addEmbeddedImage('zskam.png', 'header_logo');
                        
                        // Přidání loga jako přílohy (patička) - ODSTRANĚNO, POUŽITA URL
                        // $mail->addEmbeddedImage('logo_bezpozadi.png', 'footer_logo');
                    
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