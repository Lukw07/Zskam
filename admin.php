<?php
require_once 'auth.php';
require_once 'db.php';
redirect_if_not_logged_in();
if (!is_admin()) die("Přístup odepřen");

// Správa uživatelů
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'create':
            $name = $conn->real_escape_string($_POST['name']);
            $email = $conn->real_escape_string($_POST['email']);
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $role = $_POST['role'] === 'admin' ? 'admin' : 'user';
            
            $stmt = $conn->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $email, $password, $role);
            $stmt->execute();
            break;
            
        case 'update':
            $id = intval($_POST['id']);
            $name = $conn->real_escape_string($_POST['name']);
            $email = $conn->real_escape_string($_POST['email']);
            $role = $_POST['role'] === 'admin' ? 'admin' : 'user';
            
            if (!empty($_POST['password'])) {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, password_hash = ?, role = ? WHERE id = ?");
                $stmt->bind_param("ssssi", $name, $email, $password, $role, $id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?");
                $stmt->bind_param("sssi", $name, $email, $role, $id);
            }
            $stmt->execute();
            break;
            
        case 'delete':
            $id = intval($_POST['id']);
            $conn->query("DELETE FROM users WHERE id = $id");
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
    <meta http-equiv="refresh" content="60">
    <title>Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <style>
        @media (max-width: 768px) {
            .table-responsive {
                font-size: 0.9rem;
            }
            .btn-sm {
                padding: 0.2rem 0.4rem;
                font-size: 0.8rem;
            }
            .form-control, .form-select {
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
</body>
</html>