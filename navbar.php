<?php
require_once 'auth.php';
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <a class="navbar-brand" href="dashboard.php">
            <img src="https://zskamenicka.cz/wp-content/uploads/2025/06/ChatGPT-Image-9.-6.-2025-22_07_53.avif" alt="Rezervo Logo" height="30" class="me-2">
            Rezervo
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reservation.php">Nová rezervace</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="history.php">Historie</a>
                    </li>
                    <?php if (is_admin()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="admin.php">Správa uživatelů</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="admin_devices.php">Správa zařízení</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="admin_issues.php">Technické problémy</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="statistics.php">Statistiky</a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="settings.php">
                            <i class="fas fa-cog"></i> Nastavení
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Odhlásit</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
