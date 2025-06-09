<?php
require_once 'auth.php';
require_once 'db.php';
redirect_if_not_logged_in();
if (!is_admin()) die("Přístup odepřen");

// Zpracování změny stavu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status') {
        $issue_id = intval($_POST['issue_id']);
        $new_status = $conn->real_escape_string($_POST['status']);
        
        $stmt = $conn->prepare("UPDATE technical_issues SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $issue_id);
        
        if ($stmt->execute()) {
            $success_message = "Stav byl úspěšně aktualizován";
        } else {
            $error_message = "Chyba při aktualizaci stavu";
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
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Správa technických problémů</title>
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
                <h5 class="card-title">Filtry</h5>
                <form method="get" class="row g-3" id="filterForm">
                    <div class="col-md-3">
                        <label class="form-label">Stav:</label>
                        <select name="status" class="form-select" onchange="this.form.submit()">
                            <option value="">Všechny stavy</option>
                            <option value="nový" <?= isset($_GET['status']) && $_GET['status'] === 'nový' ? 'selected' : '' ?>>Nový</option>
                            <option value="přečteno" <?= isset($_GET['status']) && $_GET['status'] === 'přečteno' ? 'selected' : '' ?>>Přečteno</option>
                            <option value="v řešení" <?= isset($_GET['status']) && $_GET['status'] === 'v řešení' ? 'selected' : '' ?>>V řešení</option>
                            <option value="vyřešeno" <?= isset($_GET['status']) && $_GET['status'] === 'vyřešeno' ? 'selected' : '' ?>>Vyřešeno</option>
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
                        <label class="form-label">Od data:</label>
                        <input type="date" name="date_from" class="form-control" value="<?= isset($_GET['date_from']) ? htmlspecialchars($_GET['date_from']) : '' ?>" onchange="this.form.submit()">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Do data:</label>
                        <input type="date" name="date_to" class="form-control" value="<?= isset($_GET['date_to']) ? htmlspecialchars($_GET['date_to']) : '' ?>" onchange="this.form.submit()">
                    </div>
                    <div class="col-12">
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
        });
    </script>
</body>
</html> 