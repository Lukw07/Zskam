<?php
require_once 'auth.php';
require_once 'db.php';
redirect_if_not_logged_in();
if (!is_admin()) die("Přístup odepřen");

// Zpracování změny stavu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $issue_id = intval($_POST['issue_id']);
    $new_status = $conn->real_escape_string($_POST['status']);
    
    $stmt = $conn->prepare("UPDATE technical_issues SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $issue_id);
    
    if ($stmt->execute()) {
        $success_message = "Stav byl úspěšně aktualizován";
    } else {
        $error_message = "Chyba při aktualizaci stavu";
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="60">
    <title>Správa technických problémů</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <h2>Správa technických problémů</h2>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?= $success_message ?></div>
        <?php endif; ?>
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?= $error_message ?></div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Filtry</h5>
                <form method="get" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Stav:</label>
                        <select name="status" class="form-select">
                            <option value="">Všechny stavy</option>
                            <option value="nový" <?= isset($_GET['status']) && $_GET['status'] === 'nový' ? 'selected' : '' ?>>Nový</option>
                            <option value="přečteno" <?= isset($_GET['status']) && $_GET['status'] === 'přečteno' ? 'selected' : '' ?>>Přečteno</option>
                            <option value="v řešení" <?= isset($_GET['status']) && $_GET['status'] === 'v řešení' ? 'selected' : '' ?>>V řešení</option>
                            <option value="vyřešeno" <?= isset($_GET['status']) && $_GET['status'] === 'vyřešeno' ? 'selected' : '' ?>>Vyřešeno</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Naléhavost:</label>
                        <select name="urgency" class="form-select">
                            <option value="">Všechny naléhavosti</option>
                            <option value="vysoká" <?= isset($_GET['urgency']) && $_GET['urgency'] === 'vysoká' ? 'selected' : '' ?>>Vysoká</option>
                            <option value="střední" <?= isset($_GET['urgency']) && $_GET['urgency'] === 'střední' ? 'selected' : '' ?>>Střední</option>
                            <option value="nízká" <?= isset($_GET['urgency']) && $_GET['urgency'] === 'nízká' ? 'selected' : '' ?>>Nízká</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Od data:</label>
                        <input type="date" name="date_from" class="form-control" value="<?= isset($_GET['date_from']) ? $_GET['date_from'] : '' ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Do data:</label>
                        <input type="date" name="date_to" class="form-control" value="<?= isset($_GET['date_to']) ? $_GET['date_to'] : '' ?>">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Filtrovat</button>
                        <a href="admin_issues.php" class="btn btn-secondary">Zrušit filtry</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
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
                        <tbody>
                            <?php
                            // Sestavení SQL dotazu s filtry
                            $sql = "SELECT ti.*, u.name 
                                   FROM technical_issues ti 
                                   JOIN users u ON ti.user_id = u.id 
                                   WHERE 1=1";
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

                            if (!empty($_GET['date_to'])) {
                                $sql .= " AND DATE(ti.created_at) <= ?";
                                $params[] = $_GET['date_to'];
                                $types .= "s";
                            }

                            $sql .= " ORDER BY 
                                CASE 
                                    WHEN ti.status = 'nový' THEN 1
                                    WHEN ti.status = 'v řešení' THEN 2
                                    WHEN ti.status = 'přečteno' THEN 3
                                    ELSE 4
                                END,
                                CASE ti.urgency
                                    WHEN 'vysoká' THEN 1
                                    WHEN 'střední' THEN 2
                                    ELSE 3
                                END,
                                ti.created_at DESC";

                            $stmt = $conn->prepare($sql);
                            if (!empty($params)) {
                                $stmt->bind_param($types, ...$params);
                            }
                            $stmt->execute();
                            $issues = $stmt->get_result();
                            
                            while ($issue = $issues->fetch_assoc()):
                            ?>
                            <tr>
                                <td><?= $issue['id'] ?></td>
                                <td><?= htmlspecialchars($issue['name']) ?></td>
                                <td><?= htmlspecialchars($issue['class']) ?></td>
                                <td><?= htmlspecialchars($issue['description']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $issue['urgency'] === 'vysoká' ? 'danger' : ($issue['urgency'] === 'střední' ? 'warning' : 'info') ?>">
                                        <?= $issue['urgency'] ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $issue['status'] === 'nový' ? 'primary' : ($issue['status'] === 'vyřešeno' ? 'success' : 'secondary') ?>">
                                        <?= $issue['status'] ?>
                                    </span>
                                </td>
                                <td><?= date('d.m.Y H:i', strtotime($issue['created_at'])) ?></td>
                                <td>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="issue_id" value="<?= $issue['id'] ?>">
                                        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                            <option value="nový" <?= $issue['status'] === 'nový' ? 'selected' : '' ?>>Nový</option>
                                            <option value="přečteno" <?= $issue['status'] === 'přečteno' ? 'selected' : '' ?>>Přečteno</option>
                                            <option value="v řešení" <?= $issue['status'] === 'v řešení' ? 'selected' : '' ?>>V řešení</option>
                                            <option value="vyřešeno" <?= $issue['status'] === 'vyřešeno' ? 'selected' : '' ?>>Vyřešeno</option>
                                        </select>
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
</body>
</html> 