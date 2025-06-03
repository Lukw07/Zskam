<?php
require_once 'db.php';
session_start();

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
        
        // Získání jména uživatele pro email
        $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user_name = $stmt->get_result()->fetch_assoc()['name'];
        
        // Odeslání emailu
        $to = "kry.tuma@gmail.com";
        $subject = "Nový technický problém - " . $class;
        
        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { padding: 20px; }
                .header { background: #f8f9fa; padding: 10px; margin-bottom: 20px; }
                .content { line-height: 1.6; }
                .urgency { font-weight: bold; }
                .footer { margin-top: 20px; font-size: 0.9em; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Nový technický problém</h2>
                </div>
                <div class='content'>
                    <p><strong>Třída:</strong> {$class}</p>
                    <p><strong>Naléhavost:</strong> <span class='urgency'>{$urgency}</span></p>
                    <p><strong>Popis problému:</strong></p>
                    <p>{$description}</p>
                    <p><strong>Nahlásil:</strong> {$user_name}</p>
                </div>
                <div class='footer'>
                    <p>Tento email byl automaticky vygenerován systémem pro správu technických problémů.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Hlavičky pro HTML email
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: Rezervační systém <noreply@zskamenicka.cz>" . "\r\n";
        $headers .= "Reply-To: noreply@zskamenicka.cz" . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        if (mail($to, $subject, $message, $headers)) {
            $success_message = "Problém byl úspěšně nahlášen";
        } else {
            $error_message = "Chyba při nahlášení problému";
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
    <title>Přihlášení</title>
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