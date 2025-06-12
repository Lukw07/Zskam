<?php
require_once 'auth.php';
require_once 'db.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

redirect_if_not_logged_in();

$success_message = '';
$error_message = '';

// Zpracování formuláře pro změnu jména
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_name') {
        $name = $conn->real_escape_string($_POST['name']);
        
        // Kontrola existence jména (kromě aktuálního uživatele)
        $stmt = $conn->prepare("SELECT id FROM users WHERE name = ? AND id != ?");
        $stmt->bind_param("si", $name, $_SESSION['user_id']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error_message = "Uživatel s tímto jménem již existuje";
        } else {
            $stmt = $conn->prepare("UPDATE users SET name = ? WHERE id = ?");
            $stmt->bind_param("si", $name, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $_SESSION['name'] = $name;
                $success_message = "Jméno bylo úspěšně aktualizováno";
            } else {
                $error_message = "Chyba při aktualizaci jména";
            }
        }
    }
    // Zpracování změny hesla
    elseif ($_POST['action'] === 'update_password') {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($new_password === $confirm_password) {
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->bind_param("si", $password_hash, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $success_message = "Heslo bylo úspěšně změněno";
            } else {
                $error_message = "Chyba při změně hesla";
            }
        } else {
            $error_message = "Nová hesla se neshodují";
        }
    }
    // Zpracování změny emailu
    elseif ($_POST['action'] === 'update_email') {
        $new_email = $conn->real_escape_string($_POST['new_email']);
        
        // Kontrola, zda email již neexistuje
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $new_email, $_SESSION['user_id']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error_message = "Tento email je již používán";
        } else {
            // Aktualizace emailu
            $stmt = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
            $stmt->bind_param("si", $new_email, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                // Odeslání potvrzovacího emailu
                $mail = new PHPMailer(true);
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
                    $mail->addAddress($new_email);
                    
                    $mail->isHTML(true);
                    $mail->Subject = "Změna emailové adresy";
                    $mail->Body = "
                        <!DOCTYPE html>
                        <html lang='cs'>
                        <head>
                            <meta charset='UTF-8'>
                            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                            <title>Změna emailové adresy</title>
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
                                    <h2>Změna emailové adresy</h2>
                                </div>
                                <div class='content'>
                                    <p>Vaše emailová adresa byla úspěšně změněna na: <strong>{$new_email}</strong></p>
                                    <p>Pokud jste tuto změnu neprováděli vy, kontaktujte prosím administrátora.</p>
                                </div>
                                <div class='footer'>
                                    <img src='https://zskamenicka.cz/wp-content/uploads/2025/06/logo_bezpozadi-1.webp' alt='Rezervo Logo' style='height: 40px; display: block; margin: 0 auto;'>
                                    <div style='margin-top: 5px;'>By Kryštof Tůma</div>
                                </div>
                            </div>
                        </body>
                        </html>
                    ";
                    
                    // Přidání loga jako přílohy (hlavička) - ODSTRANĚNO, POUŽITA URL
                    // $mail->addEmbeddedImage('zskam.png', 'header_logo');
                    
                    // Přidání loga jako přílohy (patička) - ODSTRANĚNO, POUŽITA URL
                    // $mail->addEmbeddedImage('logo_bezpozadi.png', 'footer_logo');
                    
                    // Alternativní textová verze
                    $mail->AltBody = "
                        Změna emailové adresy
                        ========================
                        
                        Vaše emailová adresa byla úspěšně změněna na: {$new_email}
                        
                        Pokud jste tuto změnu neprováděli vy, kontaktujte prosím administrátora.
                        
                        By Kryštof Tůma
                    ";
                    
                    $mail->send();
                    $success_message = "Email byl úspěšně aktualizován a odesláno potvrzení";
                } catch (Exception $e) {
                    $error_message = "Chyba při odesílání potvrzovacího emailu: {$mail->ErrorInfo}";
                }
            } else {
                $error_message = "Chyba při aktualizaci emailu";
            }
        }
    }
}

// Načtení dat uživatele
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rezervo - Nastavení</title>
    <link rel="icon" type="image/png" href="logo1.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <style>
        .settings-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .settings-card .card-header {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 1.5rem;
        }
        .settings-card .card-body {
            padding: 2rem;
        }
        .form-label {
            font-weight: 500;
            color: #1e293b;
        }
        .form-control {
            border-radius: 10px;
            padding: 0.8rem 1rem;
            border: 1px solid #e2e8f0;
        }
        .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            border: none;
            border-radius: 10px;
            padding: 0.8rem 1.5rem;
            font-weight: 500;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(59, 130, 246, 0.3);
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <h2 class="mb-4">Nastavení</h2>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i>
                <?= $success_message ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?= $error_message ?>
            </div>
        <?php endif; ?>

        <!-- Změna jména -->
        <div class="card settings-card">
            <div class="card-header">
                <h3 class="card-title mb-0">
                    <i class="fas fa-user-circle me-2"></i>
                    Změna jména
                </h3>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="action" value="update_name">
                    
                    <div class="mb-3">
                        <label class="form-label">Jméno</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>
                        Uložit jméno
                    </button>
                </form>
            </div>
        </div>

        <!-- Změna hesla -->
        <div class="card settings-card">
            <div class="card-header">
                <h3 class="card-title mb-0">
                    <i class="fas fa-lock me-2"></i>
                    Změna hesla
                </h3>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="action" value="update_password">
                    
                    <div class="mb-3">
                        <label class="form-label">Nové heslo</label>
                        <input type="password" name="new_password" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Potvrzení nového hesla</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-key me-2"></i>
                        Změnit heslo
                    </button>
                </form>
            </div>
        </div>

        <!-- Změna emailu -->
        <div class="card settings-card">
            <div class="card-header">
                <h3 class="card-title mb-0">
                    <i class="fas fa-envelope me-2"></i>
                    Změna emailu
                </h3>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="action" value="update_email">
                    
                    <div class="mb-3">
                        <label class="form-label">Nový email</label>
                        <input type="email" name="new_email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>
                        Změnit email
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'footer.php'; ?>
</body>
</html> 